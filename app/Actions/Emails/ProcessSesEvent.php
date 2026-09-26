<?php

namespace App\Actions\Emails;

use App\Enums\AutomationTrigger;
use App\Enums\EmailDeliveryStatus;
use App\Enums\EmailFailureCode;
use App\Enums\EmailProvider;
use App\Enums\SubscriberStatus;
use App\Events\SubscriberLifecycleOccurred;
use App\Models\AutomationEmailDelivery;
use App\Models\EmailDelivery;
use App\Models\EmailDeliveryAttempt;
use App\Models\EmailProviderEvent;
use App\Models\Subscriber;
use App\Models\Team;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ProcessSesEvent
{
    public function __construct(
        private RecordEmailAddressHealth $emailHealth,
        private ResolveCampaignOutcome $campaignOutcome,
    ) {}

    /** @param array<string, mixed> $payload */
    public function handle(string $eventId, array $payload, string $topicArn): void
    {
        $topicArnHash = hash('sha256', trim($topicArn));
        $unsubscribed = null;

        DB::transaction(function () use ($eventId, $payload, $topicArnHash, &$unsubscribed): void {
            $unsubscribed = null;
            $type = (string) ($payload['eventType'] ?? $payload['notificationType'] ?? 'Unknown');
            $mail = is_array($payload['mail'] ?? null) ? $payload['mail'] : [];
            $attempt = $this->findAttempt($mail, $topicArnHash);
            $automationDelivery = $attempt === null
                ? $this->findAutomationDelivery($mail, $topicArnHash)
                : null;
            $legacyDelivery = $attempt === null && $automationDelivery === null
                ? $this->findLegacyPlatformDelivery($mail, $topicArnHash)
                : null;
            $deliveryId = $attempt instanceof EmailDeliveryAttempt
                ? $attempt->email_delivery_id
                : $legacyDelivery?->id;

            $event = EmailProviderEvent::query()->firstOrCreate(
                ['event_id' => $eventId],
                [
                    'provider' => EmailProvider::AmazonSes->value,
                    'ses_sns_topic_arn_hash' => $topicArnHash,
                    'email_delivery_id' => $deliveryId,
                    'email_delivery_attempt_id' => $attempt?->id,
                    'automation_email_delivery_id' => $automationDelivery?->id,
                    'type' => $type,
                    'payload' => $payload,
                    'occurred_at' => $this->eventTimestamp($payload, $type),
                    'processed_at' => now(),
                ],
            );

            if (! $event->wasRecentlyCreated || ! in_array($type, ['Delivery', 'Bounce', 'Complaint', 'Reject'], true)) {
                return;
            }

            $occurredAt = $this->eventTimestamp($payload, $type) ?? now();

            if ($attempt instanceof EmailDeliveryAttempt) {
                $this->applyFeedback($attempt, $type, $payload, $occurredAt);

                $delivery = EmailDelivery::query()
                    ->whereKey($attempt->email_delivery_id)
                    ->lockForUpdate()
                    ->first();

                if ($delivery === null) {
                    return;
                }

                if ($this->isLatestAttempt($attempt)) {
                    $this->applyFeedback($delivery, $type, $payload, $occurredAt);

                    if ($type === 'Reject') {
                        $this->campaignOutcome->refresh($delivery->email);
                    }
                }

                $delivery->loadMissing('email.team');
                $this->recordHealth(
                    $delivery->email->team,
                    $delivery->email_address,
                    $attempt->provider,
                    $type,
                    $payload,
                    $occurredAt,
                );

                if ($this->shouldUnsubscribe($type, $payload)) {
                    $unsubscribed = $this->unsubscribeSubscriber($delivery);
                }

                return;
            }

            if ($automationDelivery instanceof AutomationEmailDelivery) {
                $this->applyFeedback($automationDelivery, $type, $payload, $occurredAt);
                $automationDelivery->loadMissing('team');
                $this->recordHealth(
                    $automationDelivery->team,
                    $automationDelivery->to_address,
                    $automationDelivery->provider,
                    $type,
                    $payload,
                    $occurredAt,
                );

                if ($this->shouldUnsubscribe($type, $payload)) {
                    $unsubscribed = $this->unsubscribeAutomationSubscriber($automationDelivery);
                }

                return;
            }

            if ($legacyDelivery === null) {
                return;
            }

            $hasNewerAttempt = EmailDeliveryAttempt::query()
                ->where('email_delivery_id', $legacyDelivery->id)
                ->exists();

            if (! $hasNewerAttempt && $legacyDelivery->provider === EmailProvider::AmazonSes->value) {
                $this->applyFeedback($legacyDelivery, $type, $payload, $occurredAt);
            }

            $legacyDelivery->loadMissing('email.team');
            $this->recordHealth(
                $legacyDelivery->email->team,
                $legacyDelivery->email_address,
                EmailProvider::AmazonSes,
                $type,
                $payload,
                $occurredAt,
            );

            if ($this->shouldUnsubscribe($type, $payload)) {
                $unsubscribed = $this->unsubscribeSubscriber($legacyDelivery);
            }
        }, attempts: 3);

        if ($unsubscribed !== null) {
            event(new SubscriberLifecycleOccurred(AutomationTrigger::Unsubscribed, $unsubscribed));
        }
    }

    /** @param array<string, mixed> $mail */
    private function findAttempt(array $mail, string $topicArnHash): ?EmailDeliveryAttempt
    {
        $tags = is_array($mail['tags'] ?? null) ? $mail['tags'] : [];
        $attemptUuid = $this->firstTag($tags, 'attempt_uuid');

        if ($attemptUuid !== null) {
            $attempt = EmailDeliveryAttempt::query()
                ->where('uuid', $attemptUuid)
                ->where('provider', EmailProvider::AmazonSes)
                ->where('ses_sns_topic_arn_hash', $topicArnHash)
                ->lockForUpdate()
                ->first();

            if ($attempt !== null) {
                return $attempt;
            }
        }

        $messageId = is_string($mail['messageId'] ?? null) ? $mail['messageId'] : null;

        if ($messageId === null) {
            return null;
        }

        return EmailDeliveryAttempt::query()
            ->where('provider', EmailProvider::AmazonSes)
            ->where('provider_message_id', $messageId)
            ->where('ses_sns_topic_arn_hash', $topicArnHash)
            ->latest('id')
            ->lockForUpdate()
            ->first();
    }

    /** @param array<string, mixed> $mail */
    private function findAutomationDelivery(array $mail, string $topicArnHash): ?AutomationEmailDelivery
    {
        $tags = is_array($mail['tags'] ?? null) ? $mail['tags'] : [];
        $deliveryUuid = $this->firstTag($tags, 'automation_delivery_uuid');

        if ($deliveryUuid !== null) {
            $delivery = AutomationEmailDelivery::query()
                ->where('uuid', $deliveryUuid)
                ->where('provider', EmailProvider::AmazonSes)
                ->where('ses_sns_topic_arn_hash', $topicArnHash)
                ->lockForUpdate()
                ->first();

            if ($delivery !== null) {
                return $delivery;
            }
        }

        $messageId = is_string($mail['messageId'] ?? null) ? $mail['messageId'] : null;

        return $messageId === null ? null : AutomationEmailDelivery::query()
            ->where('provider', EmailProvider::AmazonSes)
            ->where('provider_message_id', $messageId)
            ->where('ses_sns_topic_arn_hash', $topicArnHash)
            ->latest('id')
            ->lockForUpdate()
            ->first();
    }

    /** @param array<string, mixed> $mail */
    private function findLegacyPlatformDelivery(array $mail, string $topicArnHash): ?EmailDelivery
    {
        $globalTopicArn = config('services.ses.sns_topic_arn');

        if (! is_string($globalTopicArn)
            || blank($globalTopicArn)
            || ! hash_equals(hash('sha256', trim($globalTopicArn)), $topicArnHash)) {
            return null;
        }

        $tags = is_array($mail['tags'] ?? null) ? $mail['tags'] : [];
        $deliveryUuid = $this->firstTag($tags, 'delivery_uuid');

        if ($deliveryUuid === null) {
            return null;
        }

        $delivery = EmailDelivery::query()
            ->where('uuid', $deliveryUuid)
            ->lockForUpdate()
            ->first();

        if ($delivery === null
            || ($delivery->provider !== EmailProvider::AmazonSes->value
                && ! EmailDeliveryAttempt::query()->where('email_delivery_id', $delivery->id)->exists())) {
            return null;
        }

        return $delivery;
    }

    /** @param array<string, mixed> $tags */
    private function firstTag(array $tags, string $key): ?string
    {
        $value = $tags[$key] ?? null;

        return is_array($value) && is_string($value[0] ?? null)
            ? $value[0]
            : (is_string($value) ? $value : null);
    }

    /** @param array<string, mixed> $payload */
    private function eventTimestamp(array $payload, string $type): ?Carbon
    {
        $section = match ($type) {
            'Delivery' => 'delivery',
            'Bounce' => 'bounce',
            'Complaint' => 'complaint',
            default => 'mail',
        };
        $timestamp = data_get($payload, $section.'.timestamp') ?? data_get($payload, 'mail.timestamp');

        return is_string($timestamp) ? Carbon::parse($timestamp) : null;
    }

    /** @param array<string, mixed> $payload */
    private function applyFeedback(
        EmailDelivery|EmailDeliveryAttempt|AutomationEmailDelivery $subject,
        string $type,
        array $payload,
        CarbonInterface $occurredAt,
    ): void {
        $attributes = match ($type) {
            'Delivery' => $this->deliveryAttributes($subject, $occurredAt),
            'Bounce' => $this->bounceAttributes($subject, $payload, $occurredAt),
            'Complaint' => $this->complaintAttributes($subject, $occurredAt),
            'Reject' => $this->rejectAttributes($subject, $payload),
            default => [],
        };

        if ($attributes !== []) {
            $subject->update($attributes);
        }
    }

    /** @return array<string, mixed> */
    private function deliveryAttributes(EmailDelivery|EmailDeliveryAttempt|AutomationEmailDelivery $subject, CarbonInterface $occurredAt): array
    {
        if (in_array($subject->status, [EmailDeliveryStatus::Bounced, EmailDeliveryStatus::Complained], true)) {
            return [];
        }

        return [
            'status' => EmailDeliveryStatus::Delivered,
            'delivered_at' => $subject->delivered_at ?? $occurredAt,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function bounceAttributes(
        EmailDelivery|EmailDeliveryAttempt|AutomationEmailDelivery $subject,
        array $payload,
        CarbonInterface $occurredAt,
    ): array {
        $bounceType = data_get($payload, 'bounce.bounceType');
        $reason = data_get($payload, 'bounce.bounceSubType') ?? $bounceType;

        if ($bounceType === 'Permanent') {
            if ($subject->status === EmailDeliveryStatus::Complained) {
                return [];
            }

            return [
                'status' => EmailDeliveryStatus::Bounced,
                'bounced_at' => $subject->bounced_at ?? $occurredAt,
                'failure_reason' => $subject->failure_reason ?? $reason,
            ];
        }

        if (in_array($subject->status, [
            EmailDeliveryStatus::Delivered,
            EmailDeliveryStatus::Bounced,
            EmailDeliveryStatus::Complained,
        ], true)) {
            return [];
        }

        return [
            'status' => EmailDeliveryStatus::Delayed,
            ...($subject instanceof EmailDelivery ? ['failure_code' => EmailFailureCode::TransientBounce] : []),
            'delayed_at' => $subject->delayed_at ?? $occurredAt,
            'failure_reason' => $subject->failure_reason ?? $reason,
        ];
    }

    /** @return array<string, mixed> */
    private function complaintAttributes(EmailDelivery|EmailDeliveryAttempt|AutomationEmailDelivery $subject, CarbonInterface $occurredAt): array
    {
        return [
            'status' => EmailDeliveryStatus::Complained,
            'complained_at' => $subject->complained_at ?? $occurredAt,
        ];
    }

    /**
     * SES accepted the message but refused to send it, which it does when it
     * finds a virus. The recipient did nothing wrong, so the address is not
     * suppressed, and a later outcome for the same message is never undone.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function rejectAttributes(EmailDelivery|EmailDeliveryAttempt|AutomationEmailDelivery $subject, array $payload): array
    {
        if (in_array($subject->status, [
            EmailDeliveryStatus::Delivered,
            EmailDeliveryStatus::Bounced,
            EmailDeliveryStatus::Complained,
        ], true)) {
            return [];
        }

        $reason = data_get($payload, 'reject.reason');

        return [
            'status' => EmailDeliveryStatus::Rejected,
            ...($subject instanceof EmailDelivery ? ['failure_code' => EmailFailureCode::SesRejected] : []),
            'failure_reason' => is_string($reason) && $reason !== ''
                ? __('Amazon SES rejected the message: :reason', ['reason' => $reason])
                : __('Amazon SES rejected the message.'),
        ];
    }

    private function isLatestAttempt(EmailDeliveryAttempt $attempt): bool
    {
        return EmailDeliveryAttempt::query()
            ->where('email_delivery_id', $attempt->email_delivery_id)
            ->latest('id')
            ->value('id') === $attempt->id;
    }

    /** @param array<string, mixed> $payload */
    private function shouldUnsubscribe(string $type, array $payload): bool
    {
        return $type === 'Complaint'
            || ($type === 'Bounce' && data_get($payload, 'bounce.bounceType') === 'Permanent');
    }

    private function unsubscribeSubscriber(EmailDelivery $delivery): ?Subscriber
    {
        if ($delivery->subscriber_id === null) {
            return null;
        }

        $subscriber = Subscriber::query()
            ->whereKey($delivery->subscriber_id)
            ->where('status', SubscriberStatus::Subscribed)
            ->lockForUpdate()
            ->first();

        if ($subscriber === null) {
            return null;
        }

        $subscriber->update([
            'status' => SubscriberStatus::Unsubscribed,
            'unsubscribed_at' => now(),
        ]);

        return $subscriber;
    }

    private function unsubscribeAutomationSubscriber(AutomationEmailDelivery $delivery): ?Subscriber
    {
        if ($delivery->subscriber_id === null) {
            return null;
        }

        $subscriber = Subscriber::query()
            ->whereKey($delivery->subscriber_id)
            ->where('status', SubscriberStatus::Subscribed)
            ->lockForUpdate()
            ->first();

        if ($subscriber === null) {
            return null;
        }

        $subscriber->update([
            'status' => SubscriberStatus::Unsubscribed,
            'unsubscribed_at' => now(),
        ]);

        return $subscriber;
    }

    /** @param array<string, mixed> $payload */
    private function recordHealth(
        Team $team,
        string $email,
        EmailProvider $provider,
        string $type,
        array $payload,
        CarbonInterface $occurredAt,
    ): void {
        if ($type === 'Delivery') {
            $this->emailHealth->recordDelivered($team, $email, $provider, $occurredAt);

            return;
        }

        if ($type === 'Complaint') {
            $this->emailHealth->recordComplaint($team, $email, $provider, $occurredAt);

            return;
        }

        if ($type !== 'Bounce') {
            return;
        }

        $detail = data_get($payload, 'bounce.bounceSubType') ?? data_get($payload, 'bounce.bounceType');
        $detail = is_string($detail) ? $detail : null;

        if (data_get($payload, 'bounce.bounceType') === 'Permanent') {
            $this->emailHealth->recordPermanentBounce($team, $email, $provider, $occurredAt, $detail);

            return;
        }

        $this->emailHealth->recordTransientBounce($team, $email, $provider, $occurredAt, $detail);
    }
}
