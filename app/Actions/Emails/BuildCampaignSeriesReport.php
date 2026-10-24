<?php

namespace App\Actions\Emails;

use App\Enums\CampaignSeriesContactGroup;
use App\Enums\ConversionAttributionType;
use App\Enums\EmailDeliveryStatus;
use App\Enums\EmailProvider;
use App\Enums\EmailStatus;
use App\Models\CampaignSeries;
use App\Models\Email;
use App\Models\EmailConversionEvent;
use App\Models\EmailDelivery;
use App\Models\EmailLinkTrackingAggregate;
use App\Models\EmailTrackingAggregate;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BuildCampaignSeriesReport
{
    /**
     * How many people a drill-down list returns at most.
     */
    public const int CONTACT_LIST_LIMIT = 100;

    /**
     * @return array{summary: array<string, mixed>, campaigns: list<array<string, mixed>>, has_mixed_recipients: bool}
     */
    public function handle(CampaignSeries $campaignSeries): array
    {
        $report = $this->engagementReport($campaignSeries);
        $campaignIds = $this->campaignIds($campaignSeries);
        $uniqueRecipients = (int) $report['summary']['unique_recipients'];

        $report['summary'] = [
            ...$report['summary'],
            ...$this->conversionSummary($campaignSeries, $campaignIds, $uniqueRecipients),
        ];
        $report['campaigns'] = $this->withCampaignConversions($campaignSeries, $report['campaigns']);

        return $report;
    }

    /**
     * People in one follow-up group, at most CONTACT_LIST_LIMIT of them.
     *
     * @return array{group: string, label: string, total: int, limit: int, contacts: list<array{email: string, name: string|null, conversions: int, last_activity_at: string|null}>}
     */
    public function contactGroup(CampaignSeries $campaignSeries, CampaignSeriesContactGroup $group): array
    {
        $query = $group === CampaignSeriesContactGroup::Converted
            ? $this->convertedContactsQuery($campaignSeries)
            : $this->engagedContactsQuery($campaignSeries, $group);

        $total = DB::query()->fromSub(clone $query, 'contact_group')->count();
        $contacts = array_values($query
            ->orderBy('normalized_email')
            ->limit(self::CONTACT_LIST_LIMIT)
            ->get()
            ->map(fn (object $row): array => [
                'email' => (string) data_get($row, 'normalized_email'),
                'name' => $this->fullName(data_get($row, 'first_name'), data_get($row, 'last_name')),
                'conversions' => (int) data_get($row, 'conversions', 0),
                'last_activity_at' => $this->isoTimestamp(data_get($row, 'last_activity_at')),
            ])
            ->all());

        return [
            'group' => $group->value,
            'label' => $group->label(),
            'total' => $total,
            'limit' => self::CONTACT_LIST_LIMIT,
            'contacts' => $contacts,
        ];
    }

    /**
     * @return array{summary: array<string, int|float|string|null>, campaigns: list<array<string, mixed>>, has_mixed_recipients: bool}
     */
    private function engagementReport(CampaignSeries $campaignSeries): array
    {
        $campaigns = $campaignSeries->emails()
            ->select([
                'id', 'uuid', 'name', 'subject', 'status', 'audience_id', 'segment_id',
                'recipient_count', 'send_started_at', 'sent_at', 'updated_at', 'query_string',
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
        $ctaStats = $this->ctaStats($campaigns, $campaignSeries->primary_cta_url);
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
        $feedbackProviders = array_map(
            fn (EmailProvider $provider): string => $provider->value,
            EmailProvider::withSesFeedback(),
        );
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
     * Each campaign stores its links with its own query string applied, so the
     * CTA is matched per campaign in that tagged form.
     *
     * @param  EloquentCollection<int, Email>  $campaigns
     * @return Collection<int|string, EmailLinkTrackingAggregate>
     */
    private function ctaStats(EloquentCollection $campaigns, ?string $primaryCtaUrl): Collection
    {
        if (blank($primaryCtaUrl)) {
            return collect();
        }

        return EmailLinkTrackingAggregate::query()
            ->join('email_links', 'email_links.id', '=', 'email_link_tracking_aggregates.email_link_id')
            ->select('email_links.email_id')
            ->selectRaw('SUM(email_link_tracking_aggregates.total_clicks_count) AS total_clicks_count')
            ->selectRaw('SUM(email_link_tracking_aggregates.unique_clicks_count) AS unique_clicks_count')
            ->where(function (Builder $links) use ($campaigns, $primaryCtaUrl): void {
                // Either form: a CTA entered with the query string already on
                // it was stored as written, not tagged a second time.
                foreach ($campaigns as $campaign) {
                    $links->orWhere(fn (Builder $link) => $link
                        ->where('email_links.email_id', $campaign->id)
                        ->whereIn('email_links.url', array_unique([
                            $primaryCtaUrl,
                            BuildTrackedEmailHtml::tagUrl($primaryCtaUrl, $campaign->query_string),
                        ])));
                }
            })
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

    /**
     * Series-level conversion totals. Conversions are read from the persisted
     * attribution, so they never move when settings change. People are
     * counted once however many times they converted; value sums every event.
     *
     * @param  list<int>  $campaignIds
     * @return array<string, mixed>
     */
    private function conversionSummary(CampaignSeries $campaignSeries, array $campaignIds, int $uniqueRecipients): array
    {
        $events = EmailConversionEvent::query()->where('campaign_series_id', $campaignSeries->id);
        $conversions = (clone $events)->distinct()->count('email');
        $byType = (clone $events)
            ->select('attribution_type')
            ->selectRaw('COUNT(DISTINCT email) AS people')
            ->groupBy('attribution_type')
            ->pluck('people', 'attribution_type');
        $target = $campaignSeries->conversion_target;

        return [
            'conversion_tracking' => $campaignSeries->tracksConversions(),
            'conversions' => $conversions,
            'attributed_conversions' => (int) $byType->get(ConversionAttributionType::Click->value, 0),
            'assisted_conversions' => (int) $byType->get(ConversionAttributionType::Assisted->value, 0),
            'explicit_conversions' => (int) $byType->get(ConversionAttributionType::Explicit->value, 0),
            'conversion_rate' => $this->rate($conversions, $uniqueRecipients),
            'conversion_value' => $this->valueByCurrency(clone $events, $campaignSeries->value_currency),
            'conversion_target' => $target,
            'target_progress' => $target ? round(($conversions / $target) * 100, 1) : null,
            'contact_groups' => $this->contactGroupCounts($campaignSeries, $campaignIds, $conversions),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $campaigns
     * @return list<array<string, mixed>>
     */
    private function withCampaignConversions(CampaignSeries $campaignSeries, array $campaigns): array
    {
        $credited = EmailConversionEvent::query()
            ->join('emails', 'emails.id', '=', 'email_conversion_events.email_id')
            ->where('email_conversion_events.campaign_series_id', $campaignSeries->id)
            ->where('email_conversion_events.attribution_type', ConversionAttributionType::Click);
        $conversions = (clone $credited)
            ->select('emails.uuid')
            ->selectRaw('COUNT(DISTINCT email_conversion_events.email) AS people')
            ->groupBy('emails.uuid')
            ->pluck('people', 'uuid');
        $values = (clone $credited)
            ->whereNotNull('email_conversion_events.value_minor')
            ->select(['emails.uuid', 'email_conversion_events.currency'])
            ->selectRaw('SUM(email_conversion_events.value_minor) AS value_minor')
            ->groupBy('emails.uuid', 'email_conversion_events.currency')
            ->get()
            ->groupBy('uuid');

        $campaigns = array_map(function (array $campaign) use ($conversions, $values, $campaignSeries): array {
            $converted = (int) $conversions->get($campaign['uuid'], 0);

            return [
                ...$campaign,
                'conversions' => $converted,
                'conversion_rate' => $this->rate($converted, (int) $campaign['recipient_count']),
                'conversion_value' => $this->sortValues(
                    $values->get($campaign['uuid'], collect())
                        ->map(fn (EmailConversionEvent $row): array => [
                            'currency' => (string) $row->currency,
                            'value_minor' => (int) $row->getAttribute('value_minor'),
                        ])
                        ->values()
                        ->all(),
                    $campaignSeries->value_currency,
                ),
                'is_conversion_leader' => false,
            ];
        }, $campaigns);

        $leader = $this->conversionLeader($campaignSeries, $campaigns);

        if ($leader !== null) {
            $campaigns[$leader]['is_conversion_leader'] = true;
        }

        return $campaigns;
    }

    /**
     * The campaign that clearly leads on conversion rate, or null when there
     * is not enough comparable data: tracking must be on, at least two sent
     * campaigns must have recipients, and the leader must have a credited
     * conversion and no tie.
     *
     * @param  list<array<string, mixed>>  $campaigns
     */
    private function conversionLeader(CampaignSeries $campaignSeries, array $campaigns): ?int
    {
        if (! $campaignSeries->tracksConversions()) {
            return null;
        }

        $comparable = array_filter(
            $campaigns,
            fn (array $campaign): bool => $campaign['status'] !== EmailStatus::Draft->value
                && (int) $campaign['recipient_count'] > 0,
        );

        if (count($comparable) < 2) {
            return null;
        }

        uasort($comparable, fn (array $a, array $b): int => $b['conversion_rate'] <=> $a['conversion_rate']);
        $ranked = array_keys($comparable);
        $leader = $comparable[$ranked[0]];

        if ($leader['conversions'] < 1 || $leader['conversion_rate'] === $comparable[$ranked[1]]['conversion_rate']) {
            return null;
        }

        return $ranked[0];
    }

    /**
     * @param  Builder<EmailConversionEvent>  $events
     * @return list<array{currency: string, value_minor: int}>
     */
    private function valueByCurrency(Builder $events, ?string $primaryCurrency): array
    {
        $values = $events
            ->whereNotNull('value_minor')
            ->select('currency')
            ->selectRaw('SUM(value_minor) AS value_minor')
            ->groupBy('currency')
            ->get()
            ->map(fn (EmailConversionEvent $row): array => [
                'currency' => (string) $row->currency,
                'value_minor' => (int) $row->getAttribute('value_minor'),
            ])
            ->values()
            ->all();

        return $this->sortValues($values, $primaryCurrency);
    }

    /**
     * The series' default currency first, then the rest alphabetically.
     *
     * @param  array<int, array{currency: string, value_minor: int}>  $values
     * @return list<array{currency: string, value_minor: int}>
     */
    private function sortValues(array $values, ?string $primaryCurrency): array
    {
        usort($values, fn (array $a, array $b): int => [$a['currency'] !== $primaryCurrency, $a['currency']]
            <=> [$b['currency'] !== $primaryCurrency, $b['currency']]);

        return $values;
    }

    /**
     * @param  list<int>  $campaignIds
     * @return array<string, int>
     */
    private function contactGroupCounts(CampaignSeries $campaignSeries, array $campaignIds, int $conversions): array
    {
        $counts = $campaignIds === []
            ? null
            : DB::query()
                ->fromSub($this->recipientsQuery($campaignIds), 'recipients')
                ->leftJoinSub($this->convertedEmailsQuery($campaignSeries), 'converted', 'converted.email', '=', 'recipients.normalized_email')
                ->whereNull('converted.email')
                ->selectRaw('SUM(CASE WHEN has_clicked = 1 THEN 1 ELSE 0 END) AS clicked_not_converted')
                ->selectRaw('SUM(CASE WHEN has_clicked = 0 AND has_opened = 1 THEN 1 ELSE 0 END) AS opened_not_clicked')
                ->selectRaw('SUM(CASE WHEN has_clicked = 0 AND has_opened = 0 THEN 1 ELSE 0 END) AS not_engaged')
                ->first();

        return [
            CampaignSeriesContactGroup::Converted->value => $conversions,
            CampaignSeriesContactGroup::ClickedNotConverted->value => (int) data_get($counts, 'clicked_not_converted', 0),
            CampaignSeriesContactGroup::OpenedNotClicked->value => (int) data_get($counts, 'opened_not_clicked', 0),
            CampaignSeriesContactGroup::NotEngaged->value => (int) data_get($counts, 'not_engaged', 0),
        ];
    }

    /**
     * One row per person who was sent a campaign of the series, keyed by the
     * lowercased address so the same person in two audiences counts once.
     *
     * @param  list<int>  $campaignIds
     */
    private function recipientsQuery(array $campaignIds): QueryBuilder
    {
        return DB::table('email_deliveries')
            ->whereIn('email_id', $campaignIds)
            ->whereNotNull('sent_at')
            ->selectRaw('LOWER(email_address) AS normalized_email')
            ->selectRaw('MAX(first_name) AS first_name')
            ->selectRaw('MAX(last_name) AS last_name')
            ->selectRaw('MAX(CASE WHEN first_clicked_at IS NOT NULL THEN 1 ELSE 0 END) AS has_clicked')
            ->selectRaw('MAX(CASE WHEN first_opened_at IS NOT NULL THEN 1 ELSE 0 END) AS has_opened')
            ->selectRaw('MAX(last_clicked_at) AS last_clicked_at')
            ->selectRaw('MAX(last_opened_at) AS last_opened_at')
            ->selectRaw('MAX(sent_at) AS last_sent_at')
            ->groupByRaw('LOWER(email_address)');
    }

    private function convertedEmailsQuery(CampaignSeries $campaignSeries): QueryBuilder
    {
        return DB::table('email_conversion_events')
            ->where('campaign_series_id', $campaignSeries->id)
            ->select('email')
            ->distinct();
    }

    private function convertedContactsQuery(CampaignSeries $campaignSeries): QueryBuilder
    {
        $campaignIds = $this->campaignIds($campaignSeries);
        $converted = DB::table('email_conversion_events')
            ->where('campaign_series_id', $campaignSeries->id)
            ->select('email')
            ->selectRaw('COUNT(*) AS conversions')
            ->selectRaw('MAX(occurred_at) AS last_activity_at')
            ->groupBy('email');

        return DB::query()
            ->fromSub($converted, 'converted')
            ->leftJoinSub($this->recipientsQuery($campaignIds), 'recipients', 'recipients.normalized_email', '=', 'converted.email')
            ->select([
                'converted.email AS normalized_email',
                'recipients.first_name',
                'recipients.last_name',
                'converted.conversions',
                'converted.last_activity_at',
            ]);
    }

    private function engagedContactsQuery(CampaignSeries $campaignSeries, CampaignSeriesContactGroup $group): QueryBuilder
    {
        $campaignIds = $this->campaignIds($campaignSeries);
        $lastActivity = match ($group) {
            CampaignSeriesContactGroup::ClickedNotConverted => 'recipients.last_clicked_at',
            CampaignSeriesContactGroup::OpenedNotClicked => 'recipients.last_opened_at',
            default => 'recipients.last_sent_at',
        };

        return DB::query()
            ->fromSub($this->recipientsQuery($campaignIds), 'recipients')
            ->leftJoinSub($this->convertedEmailsQuery($campaignSeries), 'converted', 'converted.email', '=', 'recipients.normalized_email')
            ->whereNull('converted.email')
            ->when(
                $group === CampaignSeriesContactGroup::ClickedNotConverted,
                fn (QueryBuilder $query) => $query->where('recipients.has_clicked', 1),
                fn (QueryBuilder $query) => $query
                    ->where('recipients.has_clicked', 0)
                    ->where('recipients.has_opened', $group === CampaignSeriesContactGroup::OpenedNotClicked ? 1 : 0),
            )
            ->select([
                'recipients.normalized_email',
                'recipients.first_name',
                'recipients.last_name',
                DB::raw('0 AS conversions'),
                DB::raw($lastActivity.' AS last_activity_at'),
            ]);
    }

    /**
     * @return list<int>
     */
    private function campaignIds(CampaignSeries $campaignSeries): array
    {
        return array_values(array_map(intval(...), $campaignSeries->emails()->pluck('id')->all()));
    }

    private function fullName(mixed $firstName, mixed $lastName): ?string
    {
        $name = trim(implode(' ', array_filter([$firstName, $lastName], fn (mixed $part): bool => is_string($part) && $part !== '')));

        return $name === '' ? null : $name;
    }

    private function isoTimestamp(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? CarbonImmutable::parse($value, 'UTC')->toISOString() : null;
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
