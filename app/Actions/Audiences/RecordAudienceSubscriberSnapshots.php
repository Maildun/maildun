<?php

namespace App\Actions\Audiences;

use App\Models\Audience;
use App\Models\AudienceSubscriberSnapshot;

class RecordAudienceSubscriberSnapshots
{
    public function __construct(private BuildAudienceSubscriberStats $buildSubscriberStats) {}

    /**
     * Record the member counts of every finished day in the chart's window
     * that has no snapshot yet. Run just after midnight this captures the day
     * that ended; the first run, or one after the scheduler was down, fills
     * the gaps from current members as well as they can still be rebuilt.
     * Existing snapshots are never changed.
     *
     * @return int the number of days recorded
     */
    public function handle(Audience $audience): int
    {
        $yesterday = now()->startOfDay()->subDay();

        $recordedDays = AudienceSubscriberSnapshot::query()
            ->where('audience_id', $audience->id)
            ->where('day', '>=', BuildAudienceSubscriberStats::historyStart()->toDateString())
            ->pluck('day')
            ->map(fn ($day): string => $day->toDateString())
            ->flip();

        $now = now();
        $rows = $this->buildSubscriberStats
            ->reconstructDays($audience, BuildAudienceSubscriberStats::historyStart(), $yesterday)
            ->reject(fn (array $day): bool => $recordedDays->has($day['date']))
            ->map(fn (array $day): array => [
                'audience_id' => $audience->id,
                'day' => $day['date'],
                'total' => $day['total'],
                'unsubscribed' => $day['unsubscribed'],
                'pending' => $day['pending'],
                'new' => $day['new'],
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->values()
            ->all();

        return AudienceSubscriberSnapshot::query()->insertOrIgnore($rows);
    }
}
