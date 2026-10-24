<?php

namespace App\Actions\Emails;

use App\Enums\ConversionAttributionType;
use App\Enums\EmailDeliveryStatus;
use App\Models\CampaignSeries;
use App\Models\EmailDelivery;
use App\Models\Team;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Last-touch attribution for a conversion event.
 *
 * The contact's latest tracked click on a campaign in an eligible series wins
 * the conversion for that campaign. Without a click, the latest eligible
 * delivery credits its series as assisted, never a campaign. Opens never give
 * credit. A touch is eligible when its delivery was sent no later than the
 * conversion and no earlier than its series' attribution window before it.
 */
class AttributeConversionEvent
{
    /**
     * Deliveries that never reached the recipient cannot have influenced them.
     *
     * @var list<EmailDeliveryStatus>
     */
    private const array UNREACHED_STATUSES = [
        EmailDeliveryStatus::Queued,
        EmailDeliveryStatus::Sending,
        EmailDeliveryStatus::Bounced,
        EmailDeliveryStatus::Rejected,
        EmailDeliveryStatus::Failed,
        EmailDeliveryStatus::Cancelled,
    ];

    /**
     * @return array{attribution_type: ConversionAttributionType, campaign_series_id: int|null, email_id: int|null, email_delivery_id: int|null, subscriber_id: int|null, touched_at: CarbonImmutable|null}
     */
    public function handle(
        Team $team,
        string $eventKey,
        string $email,
        CarbonImmutable $occurredAt,
        ?CampaignSeries $explicitSeries = null,
    ): array {
        $series = $explicitSeries instanceof CampaignSeries
            ? [$explicitSeries]
            : $team->campaignSeries()
                ->where('conversion_event_key', $eventKey)
                ->get(['id', 'team_id', 'attribution_window_days'])
                ->all();

        $deliveries = $this->eligibleDeliveries($team, $series, $email, $occurredAt);
        $click = $this->latestClick($deliveries, $occurredAt);

        if ($click !== null) {
            return $this->result(ConversionAttributionType::Click, $click['delivery'], $click['at']);
        }

        $latestDelivery = $deliveries
            ->sort(fn (EmailDelivery $a, EmailDelivery $b): int => [$b->sent_at, $b->id] <=> [$a->sent_at, $a->id])
            ->first();

        if ($latestDelivery instanceof EmailDelivery && $latestDelivery->sent_at instanceof CarbonInterface) {
            return $this->result(
                ConversionAttributionType::Assisted,
                $latestDelivery,
                CarbonImmutable::instance($latestDelivery->sent_at),
            );
        }

        return [
            'attribution_type' => $explicitSeries instanceof CampaignSeries
                ? ConversionAttributionType::Explicit
                : ConversionAttributionType::Unattributed,
            'campaign_series_id' => $explicitSeries?->id,
            'email_id' => null,
            'email_delivery_id' => null,
            'subscriber_id' => $this->subscriberId($team, $email),
            'touched_at' => null,
        ];
    }

    /**
     * Sent deliveries to this address on live campaigns of the given series,
     * each inside its own series' window.
     *
     * @param  array<int, CampaignSeries>  $series
     * @return Collection<int, EmailDelivery>
     */
    private function eligibleDeliveries(Team $team, array $series, string $email, CarbonImmutable $occurredAt): Collection
    {
        $windows = [];

        foreach ($series as $campaignSeries) {
            $windows[$campaignSeries->id] = max(1, $campaignSeries->attribution_window_days);
        }

        if ($windows === []) {
            return collect();
        }

        return EmailDelivery::query()
            ->join('emails', 'emails.id', '=', 'email_deliveries.email_id')
            ->select([
                'email_deliveries.id',
                'email_deliveries.email_id',
                'email_deliveries.subscriber_id',
                'email_deliveries.sent_at',
                'email_deliveries.first_clicked_at',
                'email_deliveries.last_clicked_at',
                'emails.campaign_series_id',
            ])
            ->where('emails.team_id', $team->id)
            ->whereNull('emails.deleted_at')
            ->whereIn('emails.campaign_series_id', array_keys($windows))
            ->whereRaw('LOWER(email_deliveries.email_address) = ?', [$email])
            ->whereNotIn('email_deliveries.status', self::UNREACHED_STATUSES)
            ->where('email_deliveries.sent_at', '<=', $occurredAt)
            ->where('email_deliveries.sent_at', '>=', $occurredAt->subDays(max($windows)))
            ->get()
            ->filter(function (EmailDelivery $delivery) use ($windows, $occurredAt): bool {
                $windowDays = $windows[(int) $delivery->getAttribute('campaign_series_id')] ?? 0;

                return $delivery->sent_at instanceof CarbonInterface
                    && $delivery->sent_at->greaterThanOrEqualTo($occurredAt->subDays($windowDays));
            })
            ->values();
    }

    /**
     * The latest click at or before the conversion. A delivery keeps its first
     * and last click, so when the last one came after the conversion the first
     * one is the latest click known to precede it.
     *
     * @param  Collection<int, EmailDelivery>  $deliveries
     * @return array{delivery: EmailDelivery, at: CarbonImmutable}|null
     */
    private function latestClick(Collection $deliveries, CarbonImmutable $occurredAt): ?array
    {
        $latest = null;

        foreach ($deliveries as $delivery) {
            $clickedAt = collect([$delivery->last_clicked_at, $delivery->first_clicked_at])
                ->first(fn (?CarbonInterface $at): bool => $at instanceof CarbonInterface && $at->lessThanOrEqualTo($occurredAt));

            if (! $clickedAt instanceof CarbonInterface) {
                continue;
            }

            $isLater = $latest === null
                || $clickedAt->greaterThan($latest['at'])
                || ($clickedAt->equalTo($latest['at']) && $delivery->id > $latest['delivery']->id);

            if ($isLater) {
                $latest = ['delivery' => $delivery, 'at' => CarbonImmutable::instance($clickedAt)];
            }
        }

        return $latest;
    }

    /**
     * @return array{attribution_type: ConversionAttributionType, campaign_series_id: int|null, email_id: int|null, email_delivery_id: int|null, subscriber_id: int|null, touched_at: CarbonImmutable|null}
     */
    private function result(ConversionAttributionType $type, EmailDelivery $delivery, CarbonImmutable $touchedAt): array
    {
        return [
            'attribution_type' => $type,
            'campaign_series_id' => (int) $delivery->getAttribute('campaign_series_id'),
            'email_id' => $delivery->email_id,
            'email_delivery_id' => $delivery->id,
            'subscriber_id' => $delivery->subscriber_id,
            'touched_at' => $touchedAt,
        ];
    }

    /**
     * A subscriber with this address in one of the workspace's audiences, for
     * conversions without an eligible delivery.
     */
    private function subscriberId(Team $team, string $email): ?int
    {
        $subscriberId = $team->subscribers()
            ->whereRaw('LOWER(subscribers.email) = ?', [$email])
            ->orderBy('subscribers.id')
            ->value('subscribers.id');

        return $subscriberId === null ? null : (int) $subscriberId;
    }
}
