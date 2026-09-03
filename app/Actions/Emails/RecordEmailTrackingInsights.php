<?php

namespace App\Actions\Emails;

use App\Enums\EmailTrackingClassification;
use App\Enums\EmailTrackingInsightDimension;
use App\Models\EmailDelivery;
use App\Models\EmailTrackingEvent;
use App\Models\EmailTrackingInsightAggregate;
use App\Models\EmailTrackingInsightUnique;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

class RecordEmailTrackingInsights
{
    /**
     * @param  bool  $countedAsOpen  Whether the authoritative delivery projection counted an open.
     * @param  bool  $countedAsClick  Whether the authoritative delivery projection counted a click.
     */
    public function handle(
        EmailTrackingEvent $event,
        EmailDelivery $delivery,
        bool $countedAsOpen,
        bool $countedAsClick,
    ): void {
        if (! $countedAsOpen && ! $countedAsClick) {
            return;
        }

        foreach ($this->dimensions($event) as $dimension) {
            $aggregate = $this->lockAggregate(
                emailId: $delivery->email_id,
                classification: $event->classification,
                dimension: $dimension['dimension'],
                key: $dimension['key'],
                label: $dimension['label'],
            );
            $unique = $this->lockUnique($aggregate->id, $delivery->id);
            $uniqueOpen = $countedAsOpen && $unique->first_opened_at === null;
            $uniqueClick = $countedAsClick && $unique->first_clicked_at === null;
            $occurredAt = $event->occurred_at;

            $aggregate->forceFill([
                'dimension_label' => Str::limit($dimension['label'], 255, ''),
                'total_opens_count' => $aggregate->total_opens_count + (int) $countedAsOpen,
                'unique_opens_count' => $aggregate->unique_opens_count + (int) $uniqueOpen,
                'total_clicks_count' => $aggregate->total_clicks_count + (int) $countedAsClick,
                'unique_clicks_count' => $aggregate->unique_clicks_count + (int) $uniqueClick,
                'first_opened_at' => $countedAsOpen
                    ? $this->earlier($aggregate->first_opened_at, $occurredAt)
                    : $aggregate->first_opened_at,
                'last_opened_at' => $countedAsOpen
                    ? $this->later($aggregate->last_opened_at, $occurredAt)
                    : $aggregate->last_opened_at,
                'first_clicked_at' => $countedAsClick
                    ? $this->earlier($aggregate->first_clicked_at, $occurredAt)
                    : $aggregate->first_clicked_at,
                'last_clicked_at' => $countedAsClick
                    ? $this->later($aggregate->last_clicked_at, $occurredAt)
                    : $aggregate->last_clicked_at,
            ])->save();

            $unique->forceFill([
                'first_opened_at' => $uniqueOpen ? $occurredAt : $unique->first_opened_at,
                'first_clicked_at' => $uniqueClick ? $occurredAt : $unique->first_clicked_at,
            ])->save();
        }
    }

    /**
     * @return list<array{dimension: EmailTrackingInsightDimension, key: string, label: string}>
     */
    private function dimensions(EmailTrackingEvent $event): array
    {
        $classification = $event->classification;
        $dimensions = [[
            'dimension' => EmailTrackingInsightDimension::Classification,
            'key' => $classification->value,
            'label' => $classification->label(),
        ]];

        if ($event->country_code !== null) {
            $countryCode = Str::upper($event->country_code);
            $dimensions[] = [
                'dimension' => EmailTrackingInsightDimension::Country,
                'key' => $countryCode,
                'label' => $this->countryName($countryCode),
            ];

            if ($event->subdivision_code !== null || $event->subdivision_name !== null) {
                $regionKey = $event->subdivision_code ?? Str::slug((string) $event->subdivision_name);
                $dimensions[] = [
                    'dimension' => EmailTrackingInsightDimension::Region,
                    'key' => $countryCode.':'.$regionKey,
                    'label' => $event->subdivision_name ?? $regionKey.', '.$countryCode,
                ];
            }

            if ($event->city_name !== null) {
                $regionKey = $event->subdivision_code ?? 'unknown';
                $dimensions[] = [
                    'dimension' => EmailTrackingInsightDimension::City,
                    'key' => $countryCode.':'.$regionKey.':'.Str::slug($event->city_name),
                    'label' => $event->city_name.', '.($event->subdivision_name ?? $countryCode),
                ];
            }
        }

        if ($event->network_asn !== null || $event->network_name !== null) {
            $networkKey = $event->network_asn !== null
                ? 'as'.$event->network_asn
                : Str::slug((string) $event->network_name);
            $networkLabel = $event->network_name ?? 'AS'.$event->network_asn;

            if ($event->network_asn !== null && $event->network_name !== null) {
                $networkLabel .= ' · AS'.$event->network_asn;
            }

            $dimensions[] = [
                'dimension' => EmailTrackingInsightDimension::Network,
                'key' => $networkKey,
                'label' => $networkLabel,
            ];
        }

        if ($event->client_family !== null) {
            $dimensions[] = [
                'dimension' => EmailTrackingInsightDimension::Client,
                'key' => Str::slug($event->client_family),
                'label' => $event->client_family,
            ];
        }

        $dimensions[] = [
            'dimension' => EmailTrackingInsightDimension::Device,
            'key' => $event->device_type,
            'label' => Str::headline($event->device_type),
        ];

        usort(
            $dimensions,
            fn (array $left, array $right): int => [$left['dimension']->value, $left['key']]
                <=> [$right['dimension']->value, $right['key']],
        );

        return $dimensions;
    }

    private function lockAggregate(
        int $emailId,
        EmailTrackingClassification $classification,
        EmailTrackingInsightDimension $dimension,
        string $key,
        string $label,
    ): EmailTrackingInsightAggregate {
        $now = now();
        $identity = [
            'email_id' => $emailId,
            'classification' => $classification->value,
            'dimension' => $dimension->value,
            'dimension_key' => Str::limit($key, 191, ''),
        ];

        EmailTrackingInsightAggregate::query()->insertOrIgnore([
            ...$identity,
            'dimension_label' => Str::limit($label, 255, ''),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return EmailTrackingInsightAggregate::query()
            ->where($identity)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function lockUnique(int $aggregateId, int $deliveryId): EmailTrackingInsightUnique
    {
        $identity = [
            'email_tracking_insight_aggregate_id' => $aggregateId,
            'email_delivery_id' => $deliveryId,
        ];
        $now = now();

        EmailTrackingInsightUnique::query()->insertOrIgnore([
            ...$identity,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return EmailTrackingInsightUnique::query()
            ->where($identity)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function earlier(?CarbonInterface $current, CarbonInterface $candidate): CarbonInterface
    {
        return $current === null || $candidate->lessThan($current) ? $candidate : $current;
    }

    private function later(?CarbonInterface $current, CarbonInterface $candidate): CarbonInterface
    {
        return $current === null || $candidate->greaterThan($current) ? $candidate : $current;
    }

    private function countryName(string $countryCode): string
    {
        if (class_exists(\Locale::class)) {
            $name = \Locale::getDisplayRegion('-'.$countryCode, 'en');

            if (is_string($name) && $name !== '') {
                return $name;
            }
        }

        return $countryCode;
    }
}
