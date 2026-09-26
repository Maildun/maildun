<?php

namespace App\Jobs;

use App\Actions\Emails\BuildTrackedEmailHtml;
use App\Actions\Emails\RenderCampaignContent;
use App\Concerns\ThrottlesEmailDelivery;
use App\Enums\EmailDeliveryStatus;
use App\Enums\EmailFailureCode;
use App\Enums\EmailStatus;
use App\Exceptions\EmailTransportException;
use App\Mail\CampaignEmail;
use App\Models\Email;
use App\Models\EmailDelivery;
use App\Models\EmailDeliveryAttempt;
use App\Services\ResolvedEmailTransport;
use App\Services\TeamMailer;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

class SendEmailDelivery implements ShouldQueue
{
    use Batchable, Queueable, ThrottlesEmailDelivery;

    public int $tries = 3;

    public int $timeout = 45;

    /** @var list<int> */
    public array $backoff = [30, 120, 300];

    public function __construct(public int $deliveryId)
    {
        $this->onQueue(config('delivery.queues.campaigns'));
    }

    /**
     * Execute the job.
     */
    public function handle(
        BuildTrackedEmailHtml $trackedHtml,
        TeamMailer $teamMailer,
        ?RenderCampaignContent $renderer = null,
    ): void {
        $renderer ??= new RenderCampaignContent;

        $delivery = EmailDelivery::query()->with([
            'email.team',
            'email.audience',
            'email.links',
            'email.attachments',
        ])->findOrFail($this->deliveryId);

        $html = $trackedHtml->build($delivery);
        $mergeData = $delivery->merge_data ?? [];
        $subject = $renderer->text($delivery->email->subject, $mergeData);
        $plainText = $renderer->plainText($delivery->email, $mergeData);

        /** @var array{ResolvedEmailTransport, EmailDeliveryAttempt}|null $claimedAttempt */
        $claimedAttempt = DB::transaction(function () use ($delivery, $teamMailer): ?array {
            if (! $this->claim($delivery)) {
                return null;
            }

            $transport = $teamMailer->resolve($delivery->email->team);
            $attempt = $delivery->attempts()->create([
                'team_email_integration_id' => $transport->integrationId,
                'integration_uuid' => $transport->integrationUuid,
                'integration_name' => $transport->integrationName,
                'provider' => $transport->provider,
                'status' => EmailDeliveryStatus::Sending,
                'ses_configuration_set' => $transport->sesConfigurationSet,
                'ses_sns_topic_arn_hash' => $transport->sesSnsTopicArnHash,
                'send_attempted_at' => now(),
            ]);

            $delivery->forceFill([
                'provider' => $transport->provider->value,
                'uses_team_email_integration' => $transport->usesTeamEmailIntegration(),
            ])->save();

            return [$transport, $attempt];
        }, attempts: 3);

        if ($claimedAttempt === null) {
            return;
        }

        $this->markCampaignSending($delivery);

        [$transport, $attempt] = $claimedAttempt;

        try {
            $sentMessage = $teamMailer->sendResolved(
                $transport,
                $delivery->email_address,
                new CampaignEmail($delivery, $html, $attempt, $subject, $plainText),
                $delivery->email->resolvedFromAddress(),
            );
        } catch (Throwable $exception) {
            $this->failAttempt($attempt, $exception);
            $this->releaseClaim($delivery);

            throw $exception;
        }

        $sentAt = now();
        $providerMessageId = $sentMessage?->getMessageId();

        DB::transaction(function () use ($attempt, $delivery, $providerMessageId, $sentAt): void {
            EmailDeliveryAttempt::query()
                ->whereKey($attempt->id)
                ->update([
                    'provider_message_id' => $providerMessageId,
                    'sent_at' => $sentAt,
                ]);

            EmailDeliveryAttempt::query()
                ->whereKey($attempt->id)
                ->where('status', EmailDeliveryStatus::Sending)
                ->update(['status' => EmailDeliveryStatus::Sent]);

            EmailDelivery::query()
                ->whereKey($delivery->id)
                ->update([
                    'provider_message_id' => $providerMessageId,
                    'sent_at' => $sentAt,
                ]);

            EmailDelivery::query()
                ->whereKey($delivery->id)
                ->where('status', EmailDeliveryStatus::Sending)
                ->update(['status' => EmailDeliveryStatus::Sent]);
        }, attempts: 3);
    }

    public function failed(?Throwable $exception): void
    {
        $failureReason = $exception instanceof EmailTransportException
            ? $exception->getMessage()
            : __('Delivery failed.');
        $failureCode = $exception instanceof EmailTransportException
            ? $exception->failureCode
            : EmailFailureCode::Unknown;

        DB::transaction(function () use ($failureReason, $failureCode): void {
            EmailDeliveryAttempt::query()
                ->where('email_delivery_id', $this->deliveryId)
                ->where('status', EmailDeliveryStatus::Sending)
                ->update([
                    'status' => EmailDeliveryStatus::Failed,
                    'failure_reason' => $failureReason,
                ]);

            EmailDelivery::query()
                ->whereKey($this->deliveryId)
                ->whereIn('status', [EmailDeliveryStatus::Queued, EmailDeliveryStatus::Sending])
                ->update([
                    'status' => EmailDeliveryStatus::Failed,
                    'failure_reason' => $failureReason,
                    'failure_code' => $failureCode,
                ]);
        }, attempts: 3);
    }

    /** @return list<string> */
    public function tags(): array
    {
        return ['delivery:'.$this->deliveryId, 'campaign-delivery'];
    }

    /**
     * Take ownership of the delivery immediately before handing it to the mail
     * transport. Only one attempt can win, so a retry that lands here after the
     * transport already accepted the message stops instead of sending again.
     */
    private function claim(EmailDelivery $delivery): bool
    {
        return EmailDelivery::query()
            ->whereKey($delivery->id)
            ->where('status', EmailDeliveryStatus::Queued)
            ->whereNull('send_attempted_at')
            ->update([
                'status' => EmailDeliveryStatus::Sending,
                'send_attempted_at' => now(),
                'failure_reason' => null,
                'failure_code' => null,
            ]) === 1;
    }

    /**
     * Move a queued campaign to Sending once a delivery wins its claim. The
     * write is conditional so a late or duplicate job, which loses the claim
     * or lands after FinalizeEmailSend, never reopens a finished campaign.
     */
    private function markCampaignSending(EmailDelivery $delivery): void
    {
        if ($delivery->email->status !== EmailStatus::Queued) {
            return;
        }

        Email::query()
            ->whereKey($delivery->email_id)
            ->where('status', EmailStatus::Queued)
            ->update(['status' => EmailStatus::Sending]);
    }

    /**
     * Hand the delivery back when the transport refused it. Nothing reached the
     * recipient, so the queue is free to try again on the usual backoff.
     */
    private function releaseClaim(EmailDelivery $delivery): void
    {
        EmailDelivery::query()
            ->whereKey($delivery->id)
            ->where('status', EmailDeliveryStatus::Sending)
            ->update([
                'status' => EmailDeliveryStatus::Queued,
                'send_attempted_at' => null,
            ]);
    }

    private function failAttempt(EmailDeliveryAttempt $attempt, Throwable $exception): void
    {
        $failureReason = $exception instanceof EmailTransportException
            ? $exception->getMessage()
            : __('Delivery failed.');

        EmailDeliveryAttempt::query()
            ->whereKey($attempt->id)
            ->where('status', EmailDeliveryStatus::Sending)
            ->update([
                'status' => EmailDeliveryStatus::Failed,
                'failure_reason' => $failureReason,
            ]);
    }
}
