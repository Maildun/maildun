<?php

namespace App\Actions\Emails;

use App\Enums\EmailSendRunKind;
use App\Enums\EmailStatus;
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
        $email = DB::transaction(fn (): Email => $this->queue($this->lock($email)));

        return $this->dispatch($email);
    }

    /**
     * Start a scheduled draft whose time has come. The due check, the claim of
     * its schedule and the start commit together under the campaign's lock, so
     * exactly one runner starts it and a runner that dies part-way leaves the
     * draft still scheduled for the next run, never silently unscheduled.
     *
     * Returns null, without starting anything, when the draft is no longer due:
     * it was started, rescheduled or unscheduled since it was listed.
     */
    public function startScheduled(Email $email): ?Batch
    {
        $email = DB::transaction(function () use ($email): ?Email {
            $lockedEmail = $this->lock($email);

            $isDue = $lockedEmail->status === EmailStatus::Draft
                && $lockedEmail->scheduled_at !== null
                && $lockedEmail->scheduled_at->lte(now());

            return $isDue ? $this->queue($lockedEmail) : null;
        });

        return $email === null ? null : $this->dispatch($email);
    }

    private function lock(Email $email): Email
    {
        return Email::query()->with('team')->lockForUpdate()->findOrFail($email->id);
    }

    /**
     * Check the locked draft can be sent and move it to Queued. Runs inside the
     * caller's transaction, so a failed check leaves the draft untouched.
     */
    private function queue(Email $lockedEmail): Email
    {
        if ($lockedEmail->status !== EmailStatus::Draft) {
            throw ValidationException::withMessages(['email' => __('This campaign has already been queued.')]);
        }

        // Source editors render an empty body to a non-blank layout, so
        // the author's source is what proves the campaign has content.
        $missingContent = blank($lockedEmail->html)
            || ($lockedEmail->editor->usesSource() && blank($lockedEmail->source));

        if ($lockedEmail->audience_id === null || $missingContent || blank($lockedEmail->subject)) {
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
        $recipientCount = $subscribers->sendableFor($lockedEmail->team)->count();

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
            // Sending now supersedes any schedule the draft had.
            'scheduled_at' => null,
            'schedule_error' => null,
        ]);
        $lockedEmail->sendRuns()->create([
            'kind' => EmailSendRunKind::Initial,
            'recipient_count' => $recipientCount,
            'started_at' => $sendStartedAt,
        ]);

        return $lockedEmail;
    }

    private function dispatch(Email $email): Batch
    {
        try {
            $batch = Bus::batch(
                [new PrepareEmailSendChunk($email->id)],
            )
                ->name('Campaign: '.$email->name)
                ->onQueue(config('delivery.queues.campaigns'))
                ->allowFailures()
                ->before(new RecordCampaignBatch($email->id))
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
