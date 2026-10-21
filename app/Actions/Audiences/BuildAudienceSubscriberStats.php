<?php

namespace App\Actions\Audiences;

use App\Enums\SubscriberStatus;
use App\Models\Audience;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class BuildAudienceSubscriberStats
{
    public const PERIOD_DAYS = 28;

    public const WEEK_DAYS = 7;

    /**
     * @return array{
     *     total: array{value: int, change: float|null},
     *     subscribed: array{value: int, change: float|null},
     *     unsubscribed: array{value: int, change: float|null},
     *     pending: array{value: int, change: float|null},
     *     new_this_week: array{value: int, change: float|null},
     *     subscribe_rate: array{value: float, change: float|null},
     *     series: list<array{
     *         date: string,
     *         total: int,
     *         previous_total: int,
     *         subscribed: int,
     *         previous_subscribed: int,
     *         unsubscribed: int,
     *         previous_unsubscribed: int,
     *         new: int,
     *         previous_new: int,
     *         subscribe_rate: float,
     *         previous_subscribe_rate: float
     *     }>
     * }
     */
    public function handle(Audience $audience): array
    {
        $today = now()->startOfDay();
        $periodStart = $today->copy()->subDays(self::PERIOD_DAYS - 1);
        $lookbackStart = $periodStart->copy()->subDays(self::PERIOD_DAYS);
        $weekStart = $today->copy()->subDays(self::WEEK_DAYS - 1);
        $previousWeekStart = $weekStart->copy()->subDays(self::WEEK_DAYS);

        $createdByDay = $this->countsByDay($audience, 'created_at', $lookbackStart);
        $unsubscribedByDay = $this->countsByDay($audience, 'unsubscribed_at', $lookbackStart);
        $pendingByDay = $this->countsByDay($audience, 'created_at', $lookbackStart, pendingOnly: true);

        $baseline = $audience->subscribers()
            ->toBase()
            ->selectRaw('count(*) as total')
            ->selectRaw('count(case when unsubscribed_at is not null and unsubscribed_at < ? then 1 end) as unsubscribed', [$lookbackStart])
            ->selectRaw('count(case when status = ? and subscribed_at is null then 1 end) as pending', [SubscriberStatus::Subscribed->value])
            ->where('created_at', '<', $lookbackStart)
            ->first();

        $daily = $this->dailySnapshots(
            $lookbackStart,
            $today,
            $createdByDay,
            $unsubscribedByDay,
            $pendingByDay,
            (int) ($baseline->total ?? 0),
            (int) ($baseline->unsubscribed ?? 0),
            (int) ($baseline->pending ?? 0),
        );

        $currentDays = $daily->slice(-self::PERIOD_DAYS)->values();
        $previousDays = $daily->slice(0, self::PERIOD_DAYS)->values();
        $emptySnapshot = [
            'date' => $today->toDateString(),
            'total' => 0,
            'subscribed' => 0,
            'unsubscribed' => 0,
            'pending' => 0,
            'new' => 0,
            'subscribe_rate' => 0.0,
        ];

        $todaySnapshot = $currentDays->last() ?? $emptySnapshot;
        $weekAgoSnapshot = $daily->firstWhere('date', $weekStart->copy()->subDay()->toDateString())
            ?? $previousDays->last()
            ?? $emptySnapshot;

        $newThisWeek = $this->sumNewBetween($daily, $weekStart, $today);
        $newPreviousWeek = $this->sumNewBetween($daily, $previousWeekStart, $weekStart->copy()->subDay());

        return [
            'total' => $this->metric((int) $todaySnapshot['total'], (int) $weekAgoSnapshot['total']),
            'subscribed' => $this->metric((int) $todaySnapshot['subscribed'], (int) $weekAgoSnapshot['subscribed']),
            'unsubscribed' => $this->metric((int) $todaySnapshot['unsubscribed'], (int) $weekAgoSnapshot['unsubscribed']),
            'pending' => $this->metric((int) $todaySnapshot['pending'], (int) $weekAgoSnapshot['pending']),
            'new_this_week' => $this->metric($newThisWeek, $newPreviousWeek),
            'subscribe_rate' => $this->metric($todaySnapshot['subscribe_rate'], $weekAgoSnapshot['subscribe_rate']),
            'series' => array_values($currentDays->map(function (array $day, int $index) use ($previousDays): array {
                $previous = $previousDays->get($index) ?? [
                    'total' => 0,
                    'subscribed' => 0,
                    'unsubscribed' => 0,
                    'new' => 0,
                    'subscribe_rate' => 0.0,
                ];

                return [
                    'date' => $day['date'],
                    'total' => $day['total'],
                    'previous_total' => $previous['total'],
                    'subscribed' => $day['subscribed'],
                    'previous_subscribed' => $previous['subscribed'],
                    'unsubscribed' => $day['unsubscribed'],
                    'previous_unsubscribed' => $previous['unsubscribed'],
                    'new' => $day['new'],
                    'previous_new' => $previous['new'],
                    'subscribe_rate' => $day['subscribe_rate'],
                    'previous_subscribe_rate' => $previous['subscribe_rate'],
                ];
            })->values()->all()),
        ];
    }

    /**
     * Pass pendingOnly to count just the members still awaiting double opt-in
     * confirmation. They are counted as pending from the day they joined.
     *
     * @param  literal-string  $column
     * @return Collection<string, int>
     */
    private function countsByDay(Audience $audience, string $column, CarbonInterface $since, bool $pendingOnly = false): Collection
    {
        return $audience->subscribers()
            ->toBase()
            ->selectRaw("DATE({$column}) as day, count(*) as aggregate")
            ->whereNotNull($column)
            ->where($column, '>=', $since)
            ->when($pendingOnly, fn ($query) => $query
                ->where('status', SubscriberStatus::Subscribed)
                ->whereNull('subscribed_at'))
            ->groupByRaw("DATE({$column})")
            ->get()
            ->mapWithKeys(fn (object $row): array => [
                Carbon::parse($row->day)->toDateString() => (int) $row->aggregate,
            ]);
    }

    /**
     * @param  Collection<string, int>  $createdByDay
     * @param  Collection<string, int>  $unsubscribedByDay
     * @param  Collection<string, int>  $pendingByDay
     * @return Collection<int, array{date: string, total: int, subscribed: int, unsubscribed: int, pending: int, new: int, subscribe_rate: float}>
     */
    private function dailySnapshots(
        CarbonInterface $from,
        CarbonInterface $to,
        Collection $createdByDay,
        Collection $unsubscribedByDay,
        Collection $pendingByDay,
        int $total,
        int $unsubscribed,
        int $pending,
    ): Collection {
        $days = collect();

        foreach (CarbonPeriod::create($from, $to) as $date) {
            $key = $date->toDateString();
            $total += $createdByDay->get($key, 0);
            $unsubscribed += $unsubscribedByDay->get($key, 0);
            $pending += $pendingByDay->get($key, 0);
            $subscribed = max($total - $unsubscribed - $pending, 0);

            $days->push([
                'date' => $key,
                'total' => $total,
                'subscribed' => $subscribed,
                'unsubscribed' => $unsubscribed,
                'pending' => $pending,
                'new' => $createdByDay->get($key, 0),
                'subscribe_rate' => $total === 0
                    ? 0.0
                    : round(($subscribed / $total) * 100, 1),
            ]);
        }

        return $days;
    }

    /**
     * @param  Collection<int, array{date: string, total: int, subscribed: int, unsubscribed: int, pending: int, new: int, subscribe_rate: float}>  $daily
     */
    private function sumNewBetween(Collection $daily, CarbonInterface $from, CarbonInterface $to): int
    {
        $fromDate = $from->toDateString();
        $toDate = $to->toDateString();

        return (int) $daily
            ->filter(fn (array $day): bool => $day['date'] >= $fromDate && $day['date'] <= $toDate)
            ->sum('new');
    }

    /**
     * @template TValue of int|float
     *
     * @param  TValue  $current
     * @param  TValue  $previous
     * @return array{value: TValue, change: float|null}
     */
    private function metric(int|float $current, int|float $previous): array
    {
        return [
            'value' => $current,
            'change' => $this->percentChange($current, $previous),
        ];
    }

    private function percentChange(int|float $current, int|float $previous): ?float
    {
        if ($previous == 0) {
            return $current == 0 ? 0.0 : null;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }
}
