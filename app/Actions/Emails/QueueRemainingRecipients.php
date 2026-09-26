<?php

namespace App\Actions\Emails;

use App\Enums\EmailSendRunKind;
use App\Enums\EmailStatus;
use App\Exceptions\EmailTransportException;
use App\Jobs\PrepareEmailSendChunk;
use App\Models\Email;
use App\Models\Subscriber;
use App\Services\TeamMailer;
use Illuminate\Bus\Batch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QueueRemainingRecipients
{
    public function __construct(private TeamMailer $teamMailer) {}

    /**
     * Recipients a campaign never reached because preparing the send failed
     * part-way: still sendable, no delivery row, and after the last recipient
     * the loader got to. Zero when the send was fully prepared.
     */
    public function remainingCount(Email $email): int
    {
        if ($email->status->isActive() || $email->status === EmailStatus::Draft) {
            return 0;
        }

        if ($email->deliveries()->count() >= $email->recipient_count) {
            return 0;
        }

        return $this->remaining($email)->count();
    }

    /**
     * Continue loading where the failed loader stopped, as a new send run.
     * The loader pages by subscriber id and skips existing delivery rows, and
     * a delivery can only be claimed once, so nobody is mailed twice.
     */
    public function handle(Email $email): Batch
    {
        $run = DB::transaction(function () use ($email) {
            $lockedEmail = Email::query()->with(['team', 'audience', 'segment'])->lockForUpdate()->findOrFail($email->id);
            $remaining = $this->remainingCount($lockedEmail);

            if ($remaining === 0) {
                throw ValidationException::withMessages(['email' => __('Every recipient of this campaign has already been queued.')]);
            }

            try {
                $transport = $this->teamMailer->resolve($lockedEmail->team);
            } catch (EmailTransportException) {
                throw ValidationException::withMessages(['email' => __('Connect an email provider before queueing the remaining recipients.')]);
            }

            if (! $transport->allowsSender($lockedEmail->resolvedFromAddress())) {
                throw ValidationException::withMessages(['email' => __(
                    'The From address :address is not verified for the current email delivery connection. Retest this sender first.',
                    ['address' => $lockedEmail->resolvedFromAddress()],
                )]);
            }

            $lockedEmail->update(['status' => EmailStatus::Queued]);

            return [
                'after' => (int) $lockedEmail->deliveries()->max('subscriber_id'),
                'run' => $lockedEmail->sendRuns()->create([
                    'kind' => EmailSendRunKind::Resume,
                    'recipient_count' => $remaining,
                    'started_at' => now(),
                ]),
            ];
        });

        $batch = Bus::batch([new PrepareEmailSendChunk($email->id, $run['after'])])
            ->name('Campaign remaining recipients: '.$email->name)
            ->onQueue(config('delivery.queues.campaigns'))
            ->allowFailures()
            ->finally(new FinalizeEmailSend($email->id))
            ->dispatch();

        $email->update(['batch_id' => $batch->id]);

        return $batch;
    }

    /** @return Builder<Subscriber> */
    private function remaining(Email $email): Builder
    {
        $relation = $email->segment_id !== null && $email->segment !== null
            ? $email->segment->subscribers()
            : $email->audience?->subscribers();

        if ($relation === null) {
            return Subscriber::query()->whereRaw('1 = 0');
        }

        $after = (int) $email->deliveries()->max('subscriber_id');

        return $relation->getQuery()
            ->sendableFor($email->team)
            ->where('subscribers.id', '>', $after)
            ->whereNotExists(fn ($query) => $query
                ->selectRaw('1')
                ->from('email_deliveries')
                ->whereColumn('email_deliveries.subscriber_id', 'subscribers.id')
                ->where('email_deliveries.email_id', $email->id));
    }
}
