<?php

namespace App\Jobs;

use App\Concerns\ThrottlesEmailDelivery;
use App\Enums\EmailDeliveryStatus;
use App\Mail\TransactionalEmailMessage;
use App\Models\TransactionalEmailDelivery;
use App\Services\TeamMailer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SendTransactionalEmailDelivery implements ShouldQueue
{
    use Queueable, ThrottlesEmailDelivery;

    public int $tries = 3;

    public int $timeout = 45;

    /** @var list<int> */
    public array $backoff = [30, 120, 300];

    public function __construct(public int $deliveryId)
    {
        $this->onQueue(config('delivery.queues.transactional'));
    }

    /**
     * Execute the job.
     */
    public function handle(TeamMailer $teamMailer): void
    {
        $delivery = TransactionalEmailDelivery::query()->with('team')->findOrFail($this->deliveryId);

        if (! $this->claim($delivery)) {
            return;
        }

        try {
            $transport = $teamMailer->resolve($delivery->team);

            $delivery->forceFill([
                'provider' => $transport->provider->value,
                'uses_team_email_integration' => $transport->usesTeamEmailIntegration(),
            ])->save();

            $sentMessage = $teamMailer->sendResolved(
                $transport,
                $delivery->to_address,
                new TransactionalEmailMessage($delivery),
                $delivery->from_address,
            );
        } catch (Throwable $exception) {
            $this->releaseClaim($delivery);

            throw $exception;
        }

        TransactionalEmailDelivery::query()
            ->whereKey($delivery->id)
            ->where('status', EmailDeliveryStatus::Sending)
            ->update([
                'status' => EmailDeliveryStatus::Sent,
                'provider_message_id' => $sentMessage?->getMessageId(),
                'sent_at' => now(),
            ]);
    }

    public function failed(?Throwable $exception): void
    {
        TransactionalEmailDelivery::query()->whereKey($this->deliveryId)->update([
            'status' => EmailDeliveryStatus::Failed,
            'failure_reason' => $exception?->getMessage() ?? __('Delivery failed.'),
        ]);
    }

    /** @return list<string> */
    public function tags(): array
    {
        return ['transactional-delivery:'.$this->deliveryId];
    }

    private function claim(TransactionalEmailDelivery $delivery): bool
    {
        return TransactionalEmailDelivery::query()
            ->whereKey($delivery->id)
            ->where('status', EmailDeliveryStatus::Queued)
            ->whereNull('send_attempted_at')
            ->update([
                'status' => EmailDeliveryStatus::Sending,
                'send_attempted_at' => now(),
                'failure_reason' => null,
            ]) === 1;
    }

    private function releaseClaim(TransactionalEmailDelivery $delivery): void
    {
        TransactionalEmailDelivery::query()
            ->whereKey($delivery->id)
            ->where('status', EmailDeliveryStatus::Sending)
            ->update([
                'status' => EmailDeliveryStatus::Queued,
                'send_attempted_at' => null,
            ]);
    }
}
