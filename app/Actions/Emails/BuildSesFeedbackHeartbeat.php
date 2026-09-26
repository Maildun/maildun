<?php

namespace App\Actions\Emails;

use App\Enums\EmailProvider;
use App\Models\EmailDeliveryAttempt;
use App\Models\EmailProviderEvent;
use App\Models\TeamEmailIntegration;
use Illuminate\Support\Carbon;

class BuildSesFeedbackHeartbeat
{
    /**
     * SES usually reports Delivery or Bounce within minutes. A campaign sent
     * longer ago than this with no feedback since means the SNS topic is no
     * longer reaching Maildun.
     */
    public const int GRACE_MINUTES = 60;

    /**
     * When SES last reported back through this connection's SNS topic, and
     * whether mail has gone out since without any feedback arriving. Null
     * for providers that do not report feedback.
     *
     * @return array{last_feedback_at: string|null, last_sent_at: string|null, stale: bool}|null
     */
    public function handle(TeamEmailIntegration $integration): ?array
    {
        if ($integration->provider !== EmailProvider::AmazonSes || $integration->ses_sns_topic_arn_hash === null) {
            return null;
        }

        $lastFeedbackAt = $this->timestamp(EmailProviderEvent::query()
            ->where('ses_sns_topic_arn_hash', $integration->ses_sns_topic_arn_hash)
            ->max('created_at'));
        $attempts = EmailDeliveryAttempt::query()->where('team_email_integration_id', $integration->id);
        $lastSentAt = $this->timestamp((clone $attempts)->max('sent_at'));
        $lastSentBeforeGrace = $this->timestamp((clone $attempts)
            ->where('sent_at', '<=', now()->subMinutes(self::GRACE_MINUTES))
            ->max('sent_at'));

        return [
            'last_feedback_at' => $lastFeedbackAt?->toISOString(),
            'last_sent_at' => $lastSentAt?->toISOString(),
            'stale' => $lastSentBeforeGrace !== null
                && ($lastFeedbackAt === null || $lastFeedbackAt->lt($lastSentBeforeGrace)),
        ];
    }

    private function timestamp(mixed $value): ?Carbon
    {
        return is_string($value) || $value instanceof \DateTimeInterface
            ? Carbon::parse($value)
            : null;
    }
}
