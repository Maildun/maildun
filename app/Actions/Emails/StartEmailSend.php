<?php

namespace App\Actions\Emails;

use App\Enums\EmailStatus;
use App\Enums\SubscriberStatus;
use App\Exceptions\EmailTransportException;
use App\Jobs\PrepareEmailSendChunk;
use App\Models\Email;
use App\Services\ResolvedEmailTransport;
use App\Services\TeamMailer;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StartEmailSend
{
    public function __construct(
        private BuildTrackedEmailHtml $trackedHtml,
        private TeamMailer $teamMailer,
    ) {}

    public function handle(Email $email): Batch
    {
        $email = DB::transaction(function () use ($email): Email {
            $lockedEmail = Email::query()->with('team')->lockForUpdate()->findOrFail($email->id);

            if ($lockedEmail->status !== EmailStatus::Draft) {
                throw ValidationException::withMessages(['email' => __('This campaign has already been queued.')]);
            }

            if ($lockedEmail->audience_id === null || blank($lockedEmail->html) || blank($lockedEmail->subject)) {
                throw ValidationException::withMessages(['email' => __('Choose recipients and complete the subject and content before sending.')]);
            }

            try {
                $transport = $this->teamMailer->resolve($lockedEmail->team);
            } catch (EmailTransportException) {
                throw ValidationException::withMessages(['email' => __('Connect an email provider before sending campaigns.')]);
            }

            $this->assertSenderIsAuthorized($transport, $lockedEmail->resolvedFromAddress());

            $subscribers = $lockedEmail->segment_id
                ? $lockedEmail->segment->subscribers()
                : $lockedEmail->audience->subscribers();
            $recipientCount = $subscribers->where('status', SubscriberStatus::Subscribed)->count();

            if ($recipientCount === 0) {
                throw ValidationException::withMessages(['email' => __('This campaign has no subscribed recipients.')]);
            }

            $sendStartedAt = now();
            $lockedEmail->links()->delete();
            $lockedEmail->trackingAggregate()->updateOrCreate([], [
                'total_opens_count' => 0,
                'unique_opens_count' => 0,
                'total_clicks_count' => 0,
                'unique_clicks_count' => 0,
                'revision' => 0,
                'first_opened_at' => null,
                'last_opened_at' => null,
                'first_clicked_at' => null,
                'last_clicked_at' => null,
            ]);

            if ($lockedEmail->track_clicks) {
                foreach ($this->trackedHtml->extractLinks($lockedEmail->html ?? '', $lockedEmail->query_string) as $position => $url) {
                    $link = $lockedEmail->links()->create([
                        'url' => $url,
                        'url_hash' => hash('sha256', $url),
                        'position' => $position,
                    ]);
                    $link->trackingAggregate()->create();
                }
            }

            $lockedEmail->update([
                'status' => EmailStatus::Queued,
                'recipient_count' => $recipientCount,
                'send_started_at' => $sendStartedAt,
            ]);

            return $lockedEmail;
        });

        try {
            $batch = Bus::batch(
                [new PrepareEmailSendChunk($email->id)],
            )
                ->name('Campaign: '.$email->name)
                ->onQueue(config('delivery.queues.campaigns'))
                ->allowFailures()
                ->finally(new FinalizeEmailSend($email->id))
                ->dispatch();

            $email->update(['batch_id' => $batch->id]);

            return $batch;
        } catch (\Throwable $exception) {
            $email->update(['status' => EmailStatus::Failed]);

            throw $exception;
        }
    }

    /**
     * Stop a campaign whose From address is not a sender verified for the
     * current connection, rather than failing every recipient at the provider.
     */
    private function assertSenderIsAuthorized(ResolvedEmailTransport $transport, string $fromAddress): void
    {
        if ($transport->allowsSender($fromAddress)) {
            return;
        }

        throw ValidationException::withMessages(['email' => __(
            'The From address :address is not verified for the current email delivery connection. Retest this sender before sending.',
            ['address' => $fromAddress],
        )]);
    }
}
