<?php

namespace App\Actions\Emails;

use App\Enums\EmailDeliveryStatus;
use App\Enums\EmailStatus;
use App\Enums\SubscriberStatus;
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
     * @param  list<int>|null  $deliveryIds
     */
    public function handle(Email $email, ?array $deliveryIds = null): Batch
    {
        $deliveries = DB::transaction(function () use ($email, $deliveryIds) {
            $lockedEmail = Email::query()->with('team')->lockForUpdate()->findOrFail($email->id);

            if ($lockedEmail->status === EmailStatus::Draft) {
                throw ValidationException::withMessages(['email' => __('This campaign has not been sent.')]);
            }

            if ($lockedEmail->status->isActive()) {
                throw ValidationException::withMessages(['email' => __('Wait until this campaign finishes sending before retrying.')]);
            }

            $query = $lockedEmail->deliveries()
                ->whereIn('status', EmailDeliveryStatus::retryable())
                ->where(function (Builder $deliveries): void {
                    $deliveries
                        ->whereNull('subscriber_id')
                        ->orWhereHas(
                            'subscriber',
                            fn (Builder $subscriber) => $subscriber->where('status', SubscriberStatus::Subscribed),
                        );
                });

            if ($deliveryIds !== null) {
                $query->whereIn('id', $deliveryIds);
            }

            $deliveries = $query->lockForUpdate()->get();

            if ($deliveries->isEmpty()) {
                throw ValidationException::withMessages(['email' => __('There are no failed deliveries that can be retried.')]);
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

            $lockedEmail->deliveries()
                ->whereKey($deliveries->modelKeys())
                ->update([
                    'status' => EmailDeliveryStatus::Queued,
                    'failure_reason' => null,
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
