<?php

namespace App\Actions\Emails;

use App\Enums\EmailDeliveryStatus;
use App\Enums\EmailProvider;
use App\Enums\EmailStatus;
use App\Models\CampaignSeries;
use App\Models\Email;
use App\Models\EmailDelivery;
use App\Models\EmailLinkTrackingAggregate;
use App\Models\EmailTrackingAggregate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BuildCampaignSeriesReport
{
    /**
     * @return array{summary: array<string, int|float|string|null>, campaigns: list<array<string, mixed>>, has_mixed_recipients: bool}
     */
    public function handle(CampaignSeries $campaignSeries): array
    {
        $campaigns = $campaignSeries->emails()
            ->select([
                'id', 'uuid', 'name', 'subject', 'status', 'audience_id', 'segment_id',
                'recipient_count', 'send_started_at', 'sent_at', 'updated_at',
            ])
            ->with([
                'audience:id,uuid,name',
                'segment:id,uuid,name',
                'trackingAggregate:id,email_id,unique_opens_count,unique_clicks_count',
            ])
            ->orderByRaw('CASE WHEN sent_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('sent_at')
            ->orderBy('id')
            ->get();

        if ($campaigns->isEmpty()) {
            return [
                'summary' => $this->emptySummary(),
                'campaigns' => [],
                'has_mixed_recipients' => false,
            ];
        }

        /** @var list<int> $campaignIds */
        $campaignIds = array_values(array_map(intval(...), $campaigns->modelKeys()));
        $deliveryStats = $this->deliveryStats($campaignIds);
        $ctaStats = $this->ctaStats($campaignIds, $campaignSeries->primary_cta_url);
        $deliveries = EmailDelivery::query()->whereIn('email_id', $campaignIds);
        $uniqueRecipients = $this->countDistinctRecipients(clone $deliveries);
        $uniqueOpened = $this->countDistinctRecipients((clone $deliveries)->whereNotNull('first_opened_at'));
        $uniqueClicked = $this->countDistinctRecipients((clone $deliveries)->whereNotNull('first_clicked_at'));
        $seriesDeliveryStats = $this->seriesDeliveryStats($deliveryStats);

        return [
            'summary' => [
                'campaigns' => $campaigns->count(),
                'sent_campaigns' => $campaigns->filter(
                    fn (Email $email): bool => $this->displayStatus($email) !== EmailStatus::Draft,
                )->count(),
                'unique_recipients' => $uniqueRecipients,
                'processed' => $seriesDeliveryStats['processed'],
                'delivered' => $seriesDeliveryStats['delivered'],
                'opened' => $uniqueOpened,
                'clicked' => $uniqueClicked,
                'cta_clicks' => $ctaStats->sum('total_clicks_count'),
                'bounced' => $seriesDeliveryStats['bounced'],
                'complained' => $seriesDeliveryStats['complained'],
                'failed' => $seriesDeliveryStats['failed'],
                'delivery_feedback' => $this->deliveryFeedback(
                    $seriesDeliveryStats['ses_recipients'],
                    $seriesDeliveryStats['deliveries'],
                ),
                'feedback_recipient_count' => $seriesDeliveryStats['ses_recipients'],
                'delivery_rate' => $this->rate(
                    $seriesDeliveryStats['delivered'],
                    $seriesDeliveryStats['ses_recipients'],
                    null,
                ),
                'open_rate' => $this->rate($uniqueOpened, $uniqueRecipients),
                'click_rate' => $this->rate($uniqueClicked, $uniqueRecipients),
                'click_to_open_rate' => $this->rate($uniqueClicked, $uniqueOpened),
            ],
            'campaigns' => array_values($campaigns
                ->map(fn (Email $email): array => $this->campaignPayload(
                    $email,
                    $deliveryStats->get($email->id),
                    $ctaStats->get($email->id),
                ))
                ->all()),
            'has_mixed_recipients' => $campaigns
                ->map(fn (Email $email): string => ($email->audience_id ?? 'none').':'.($email->segment_id ?? 'all'))
                ->unique()
                ->count() > 1,
        ];
    }

    /** @return array<string, int|float|string|null> */
    private function emptySummary(): array
    {
        return [
            'campaigns' => 0,
            'sent_campaigns' => 0,
            'unique_recipients' => 0,
            'processed' => 0,
            'delivered' => 0,
            'opened' => 0,
            'clicked' => 0,
            'cta_clicks' => 0,
            'bounced' => 0,
            'complained' => 0,
            'failed' => 0,
            'delivery_feedback' => 'unavailable',
            'feedback_recipient_count' => 0,
            'delivery_rate' => null,
            'open_rate' => 0.0,
            'click_rate' => 0.0,
            'click_to_open_rate' => 0.0,
        ];
    }

    /**
     * @param  list<int>  $campaignIds
     * @return Collection<int|string, EmailDelivery>
     */
    private function deliveryStats(array $campaignIds): Collection
    {
        $feedbackProviders = [EmailProvider::AmazonSes->value];
        $feedbackProviderList = implode(', ', array_fill(0, count($feedbackProviders), '?'));

        return EmailDelivery::query()
            ->select('email_id')
            ->selectRaw('COUNT(*) AS deliveries_count')
            ->selectRaw(
                'COUNT(CASE WHEN status NOT IN (?, ?) THEN 1 END) AS processed_count',
                [EmailDeliveryStatus::Queued->value, EmailDeliveryStatus::Sending->value],
            )
            ->selectRaw(
                "COUNT(CASE WHEN provider IN ({$feedbackProviderList}) THEN 1 END) AS ses_recipients_count",
                $feedbackProviders,
            )
            ->selectRaw(
                "COUNT(CASE WHEN provider IN ({$feedbackProviderList}) AND delivered_at IS NOT NULL THEN 1 END) AS delivered_count",
                $feedbackProviders,
            )
            ->selectRaw('COUNT(CASE WHEN status = ? THEN 1 END) AS bounced_count', [
                EmailDeliveryStatus::Bounced->value,
            ])
            ->selectRaw('COUNT(CASE WHEN status = ? THEN 1 END) AS complained_count', [
                EmailDeliveryStatus::Complained->value,
            ])
            ->selectRaw('COUNT(CASE WHEN status IN (?, ?) THEN 1 END) AS failed_count', [
                EmailDeliveryStatus::Failed->value,
                EmailDeliveryStatus::Rejected->value,
            ])
            ->whereIn('email_id', $campaignIds)
            ->groupBy('email_id')
            ->get()
            ->keyBy('email_id')
            ->toBase();
    }

    /**
     * @param  list<int>  $campaignIds
     * @return Collection<int|string, EmailLinkTrackingAggregate>
     */
    private function ctaStats(array $campaignIds, ?string $primaryCtaUrl): Collection
    {
        if (blank($primaryCtaUrl)) {
            return collect();
        }

        return EmailLinkTrackingAggregate::query()
            ->join('email_links', 'email_links.id', '=', 'email_link_tracking_aggregates.email_link_id')
            ->select('email_links.email_id')
            ->selectRaw('SUM(email_link_tracking_aggregates.total_clicks_count) AS total_clicks_count')
            ->selectRaw('SUM(email_link_tracking_aggregates.unique_clicks_count) AS unique_clicks_count')
            ->whereIn('email_links.email_id', $campaignIds)
            ->where('email_links.url', $primaryCtaUrl)
            ->groupBy('email_links.email_id')
            ->get()
            ->keyBy('email_id')
            ->toBase();
    }

    /** @param Builder<EmailDelivery> $query */
    private function countDistinctRecipients(Builder $query): int
    {
        $normalizedRecipients = $query
            ->selectRaw('LOWER(email_address) AS normalized_email')
            ->distinct()
            ->toBase();

        return DB::query()->fromSub($normalizedRecipients, 'normalized_recipients')->count();
    }

    /**
     * @param  Collection<int|string, EmailDelivery>  $deliveryStats
     * @return array{deliveries: int, processed: int, ses_recipients: int, delivered: int, bounced: int, complained: int, failed: int}
     */
    private function seriesDeliveryStats(Collection $deliveryStats): array
    {
        return [
            'deliveries' => (int) $deliveryStats->sum('deliveries_count'),
            'processed' => (int) $deliveryStats->sum('processed_count'),
            'ses_recipients' => (int) $deliveryStats->sum('ses_recipients_count'),
            'delivered' => (int) $deliveryStats->sum('delivered_count'),
            'bounced' => (int) $deliveryStats->sum('bounced_count'),
            'complained' => (int) $deliveryStats->sum('complained_count'),
            'failed' => (int) $deliveryStats->sum('failed_count'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function campaignPayload(Email $email, mixed $deliveryStats, mixed $ctaStats): array
    {
        $trackingAggregate = $email->getRelation('trackingAggregate');
        $opened = $trackingAggregate instanceof EmailTrackingAggregate
            ? $trackingAggregate->unique_opens_count
            : 0;
        $clicked = $trackingAggregate instanceof EmailTrackingAggregate
            ? $trackingAggregate->unique_clicks_count
            : 0;
        $recipientCount = $email->recipient_count;
        $sesRecipients = (int) data_get($deliveryStats, 'ses_recipients_count', 0);

        return [
            'uuid' => $email->uuid,
            'name' => $email->name,
            'subject' => $email->subject,
            'status' => $this->displayStatus($email)->value,
            'audience' => $email->audience?->name,
            'segment' => $email->segment?->name,
            'recipient_count' => $recipientCount,
            'processed' => (int) data_get($deliveryStats, 'processed_count', 0),
            'delivered' => (int) data_get($deliveryStats, 'delivered_count', 0),
            'opened' => $opened,
            'clicked' => $clicked,
            'cta_clicks' => (int) data_get($ctaStats, 'total_clicks_count', 0),
            'bounced' => (int) data_get($deliveryStats, 'bounced_count', 0),
            'complained' => (int) data_get($deliveryStats, 'complained_count', 0),
            'failed' => (int) data_get($deliveryStats, 'failed_count', 0),
            'delivery_feedback' => $this->deliveryFeedback($sesRecipients, $recipientCount),
            'delivery_rate' => $this->rate(
                (int) data_get($deliveryStats, 'delivered_count', 0),
                $sesRecipients,
                null,
            ),
            'open_rate' => $this->rate($opened, $recipientCount),
            'click_rate' => $this->rate($clicked, $recipientCount),
            'click_to_open_rate' => $this->rate($clicked, $opened),
            'sent_at' => $email->sent_at?->toISOString(),
            'updated_at' => $email->updated_at?->toISOString(),
        ];
    }

    private function displayStatus(Email $email): EmailStatus
    {
        return $email->status === EmailStatus::Draft && $email->sent_at
            ? EmailStatus::Sent
            : $email->status;
    }

    private function deliveryFeedback(int $sesRecipients, int $recipientCount): string
    {
        return match (true) {
            $sesRecipients === 0 => 'unavailable',
            $sesRecipients < $recipientCount => 'partial',
            default => 'available',
        };
    }

    private function rate(int $numerator, int $denominator, float|int|null $empty = 0.0): float|int|null
    {
        return $denominator === 0 ? $empty : round(($numerator / $denominator) * 100, 1);
    }
}
