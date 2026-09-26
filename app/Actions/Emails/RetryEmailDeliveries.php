<?php

namespace App\Actions\Emails;

use App\Enums\EmailDeliveryStatus;
use App\Enums\EmailSendRunKind;
use App\Enums\EmailStatus;
use App\Exceptions\EmailTransportException;
use App\Jobs\SendEmailDelivery;
use App\Models\Email;
use App\Models\EmailDelivery;
use App\Services\TeamMailer;
use Illuminate\Bus\Batch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RetryEmailDeliveries
{
    public function __construct(private TeamMailer $teamMailer) {}

    /**
     * Unconfirmed deliveries (claimed, handed to the transport, never
     * confirmed) are skipped unless the caller opts in, because the provider
     * may already have delivered them.
     *
     * @param  list<int>|null  $deliveryIds
     */
    public function handle(Email $email, ?array $deliveryIds = null, bool $includeUnconfirmed = false): Batch
    {
        $deliveries = DB::transaction(function () use ($email, $deliveryIds, $includeUnconfirmed) {
            $lockedEmail = Email::query()->with('team')->lockForUpdate()->findOrFail($email->id);

            if ($lockedEmail->status === EmailStatus::Draft) {
                throw ValidationException::withMessages(['email' => __('This campaign has not been sent.')]);
            }

            if ($lockedEmail->status->isActive()) {
                throw ValidationException::withMessages(['email' => __('Wait until this campaign finishes sending before retrying.')]);
            }

            $query = $lockedEmail->deliveries()->retryableFor($lockedEmail->team);

            if ($deliveryIds !== null) {
                $query->whereIn('id', $deliveryIds);
            }

            $deliveries = (clone $query)
                ->when(
                    ! $includeUnconfirmed,
                    fn (Builder $deliveries) => $deliveries->whereNot(fn (Builder $unconfirmed) => $unconfirmed->unconfirmed()),
                )
                ->lockForUpdate()
                ->get();

            if ($deliveries->isEmpty()) {
                throw ValidationException::withMessages(['email' => ! $includeUnconfirmed && $query->unconfirmed()->exists()
                    ? __('These deliveries may already have reached their recipients. Confirm that you want to send them again.')
                    : __('There are no failed deliveries that can be retried.')]);
            }

            try {
                $transport = $this->teamMailer->resolve($lockedEmail->team);
            } catch (EmailTransportException) {
                throw ValidationException::withMessages(['email' => __('Connect an email provider before retrying deliveries.')]);
            }

            $fromAddress = $lockedEmail->resolvedFromAddress();

            if (! $transport->allowsSender($fromAddress)) {
                throw ValidationException::withMessages(['email' => __(
                    'The From address :address is not verified for the current email delivery connection. Retest this sender before retrying.',
                    ['address' => $fromAddress],
                )]);
            }

            $sendRun = $lockedEmail->sendRuns()->create([
                'kind' => EmailSendRunKind::Retry,
                'recipient_count' => $deliveries->count(),
                'started_at' => now(),
            ]);

            $lockedEmail->deliveries()
                ->whereKey($deliveries->modelKeys())
                ->update([
                    'email_send_run_id' => $sendRun->id,
                    'status' => EmailDeliveryStatus::Queued,
                    'failure_reason' => null,
                    'failure_code' => null,
                    'provider_message_id' => null,
                    'send_attempted_at' => null,
                    'sent_at' => null,
                    'delivered_at' => null,
                    'delayed_at' => null,
                ]);

            $lockedEmail->update(['status' => EmailStatus::Sending]);

            return $deliveries;
        });

        $batch = Bus::batch(
            $deliveries->map(fn (EmailDelivery $delivery): SendEmailDelivery => new SendEmailDelivery($delivery->id)),
        )
            ->name('Campaign retry: '.$email->name)
            ->onQueue(config('delivery.queues.campaigns'))
            ->allowFailures()
            ->finally(new FinalizeEmailSend($email->id))
            ->dispatch();

        $email->update(['batch_id' => $batch->id]);

        return $batch;
    }
}
