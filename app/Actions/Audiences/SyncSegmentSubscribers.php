<?php

namespace App\Actions\Audiences;

use App\Models\Segment;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

class SyncSegmentSubscribers
{
    /**
     * Pivot rows written or removed per query, kept well below the bound
     * parameter limits of SQLite (32,766) and Postgres (65,535).
     */
    private const CHUNK_SIZE = 1000;

    public function __construct(private ApplySegmentRules $applySegmentRules) {}

    public function handle(Segment $segment): void
    {
        DB::transaction(function () use ($segment): void {
            $matchingIds = $this->applySegmentRules
                ->handle($segment->audience, $segment->audience->subscribers()->getQuery(), $segment->rules, $segment->match_type)
                ->pluck('subscribers.id')
                ->map(fn (mixed $id): int => (int) $id);
            $currentIds = DB::table('segment_subscriber')
                ->where('segment_id', $segment->id)
                ->pluck('subscriber_id')
                ->map(fn (mixed $id): int => (int) $id);

            foreach ($currentIds->diff($matchingIds)->chunk(self::CHUNK_SIZE) as $removedIds) {
                DB::table('segment_subscriber')
                    ->where('segment_id', $segment->id)
                    ->whereIn('subscriber_id', $removedIds->values()->all())
                    ->delete();
            }

            $now = Date::now();

            foreach ($matchingIds->diff($currentIds)->chunk(self::CHUNK_SIZE) as $addedIds) {
                DB::table('segment_subscriber')->insertOrIgnore($addedIds->map(fn (int $subscriberId): array => [
                    'segment_id' => $segment->id,
                    'subscriber_id' => $subscriberId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->values()->all());
            }

            $segment->forceFill(['rules_synced_at' => $now])->save();
        });
    }
}
