<?php

namespace App\Actions\Emails;

use App\Enums\EmailTrackingClassification;
use App\Enums\EmailTrackingInsightDimension;
use App\Models\Email;
use App\Models\EmailTrackingInsightAggregate;
use Illuminate\Database\Eloquent\Builder;

class BuildCampaignInsights
{
    /**
     * @return array{
     *     available: bool,
     *     human: array{opened: int, clicked: int, open_rate: float, click_rate: float},
     *     traffic: list<array{classification: string, label: string, total_opens: int, unique_opens: int, total_clicks: int, unique_clicks: int}>,
     *     locations: array{countries: list<array<string, int|string>>, regions: list<array<string, int|string>>, cities: list<array<string, int|string>>},
     *     networks: list<array<string, int|string>>,
     *     clients: list<array<string, int|string>>,
     *     devices: list<array<string, int|string>>,
     *     privacy: array{raw_ip_stored: false, event_retention_days: int},
     *     attribution: array{label: string, url: string}
     * }
     */
    public function handle(Email $email): array
    {
        $classificationAggregates = [];
        $classificationRows = $email->insightAggregates()
            ->where('dimension', EmailTrackingInsightDimension::Classification)
            ->get();

        foreach ($classificationRows as $aggregate) {
            $classificationAggregates[$aggregate->classification->value] = $aggregate;
        }

        $human = $classificationAggregates[EmailTrackingClassification::Human->value] ?? null;
        $recipientCount = max($email->recipient_count, 1);

        return [
            'available' => $classificationAggregates !== [],
            'human' => [
                'opened' => $human->unique_opens_count ?? 0,
                'clicked' => $human->unique_clicks_count ?? 0,
                'open_rate' => round((($human->unique_opens_count ?? 0) / $recipientCount) * 100, 1),
                'click_rate' => round((($human->unique_clicks_count ?? 0) / $recipientCount) * 100, 1),
            ],
            'traffic' => array_map(
                fn (EmailTrackingClassification $classification): array => $this->trafficRow(
                    $classification,
                    $classificationAggregates[$classification->value] ?? null,
                ),
                EmailTrackingClassification::cases(),
            ),
            'locations' => [
                'countries' => $this->dimensionRows($email, EmailTrackingInsightDimension::Country),
                'regions' => $this->dimensionRows($email, EmailTrackingInsightDimension::Region),
                'cities' => $this->dimensionRows($email, EmailTrackingInsightDimension::City),
            ],
            'networks' => $this->dimensionRows($email, EmailTrackingInsightDimension::Network),
            'clients' => $this->dimensionRows($email, EmailTrackingInsightDimension::Client),
            'devices' => $this->dimensionRows($email, EmailTrackingInsightDimension::Device),
            'privacy' => [
                'raw_ip_stored' => false,
                'event_retention_days' => max((int) config('tracking.retention.days'), 0),
            ],
            'attribution' => [
                'label' => 'DB-IP Lite',
                'url' => 'https://db-ip.com',
            ],
        ];
    }

    /**
     * @return array{classification: string, label: string, total_opens: int, unique_opens: int, total_clicks: int, unique_clicks: int}
     */
    private function trafficRow(
        EmailTrackingClassification $classification,
        ?EmailTrackingInsightAggregate $aggregate,
    ): array {
        return [
            'classification' => $classification->value,
            'label' => $classification->label(),
            'total_opens' => $aggregate->total_opens_count ?? 0,
            'unique_opens' => $aggregate->unique_opens_count ?? 0,
            'total_clicks' => $aggregate->total_clicks_count ?? 0,
            'unique_clicks' => $aggregate->unique_clicks_count ?? 0,
        ];
    }

    /** @return list<array{key: string, label: string, total_opens: int, unique_opens: int, total_clicks: int, unique_clicks: int}> */
    private function dimensionRows(
        Email $email,
        EmailTrackingInsightDimension $dimension,
    ): array {
        $aggregates = $email->insightAggregates()
            ->where('classification', EmailTrackingClassification::Human)
            ->where('dimension', $dimension)
            ->where(function (Builder $query): void {
                $query
                    ->where('unique_opens_count', '>', 0)
                    ->orWhere('unique_clicks_count', '>', 0);
            })
            ->orderByDesc('unique_clicks_count')
            ->orderByDesc('unique_opens_count')
            ->orderBy('dimension_label')
            ->limit(10)
            ->get([
                'dimension_key',
                'dimension_label',
                'total_opens_count',
                'unique_opens_count',
                'total_clicks_count',
                'unique_clicks_count',
            ]);
        $rows = [];

        foreach ($aggregates as $aggregate) {
            $rows[] = [
                'key' => $aggregate->dimension_key,
                'label' => $aggregate->dimension_label,
                'total_opens' => $aggregate->total_opens_count,
                'unique_opens' => $aggregate->unique_opens_count,
                'total_clicks' => $aggregate->total_clicks_count,
                'unique_clicks' => $aggregate->unique_clicks_count,
            ];
        }

        return $rows;
    }
}
