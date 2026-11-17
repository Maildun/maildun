<?php

namespace App\Actions\Audiences;

use App\Models\Segment;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

class SyncSegmentSubscribers
{
    /**
     * Pivot rows written per insert, kept well below the bound parameter
     * limits of SQLite (32,766) and Postgres (65,535).
     */
    private const CHUNK_SIZE = 1000;

    public function __construct(private ApplySegmentRules $applySegmentRules) {}

    /**
     * Sync from the segment row as it is now, locked for the length of the
     * sync. The caller's copy may be older: segments:sync loads a chunk up
     * front, and syncing a user's earlier rules would overwrite the members of
     * their edit and stamp it as synced.
     *
     * The difference is worked out by the database: members who no longer
     * match are deleted with one NOT IN subquery, and only subscribers who
     * match but are not members yet are read, a chunk at a time, to be
     * inserted. Neither full id list is loaded into PHP.
     */
    public function handle(Segment $segment): void
    {
        DB::transaction(function () use ($segment): void {
            $current = Segment::query()->with('audience')->lockForUpdate()->find($segment->id);

            if ($current === null) {
                return;
            }

            $matching = fn () => $this->applySegmentRules
                ->handle($current->audience, $current->audience->subscribers()->getQuery(), $current->rules, $current->match_type)
                ->toBase();

            DB::table('segment_subscriber')
                ->where('segment_id', $current->id)
                ->whereNotIn('subscriber_id', $matching()->select('subscribers.id'))
                ->delete();

            $now = Date::now();

            $matching()
                ->select('subscribers.id')
                ->whereNotExists(fn (Builder $members) => $members
                    ->selectRaw('1')
                    ->from('segment_subscriber')
                    ->where('segment_subscriber.segment_id', $current->id)
                    ->whereColumn('segment_subscriber.subscriber_id', 'subscribers.id'))
                ->chunkById(self::CHUNK_SIZE, function (Collection $added) use ($current, $now): void {
                    DB::table('segment_subscriber')->insertOrIgnore($added->map(fn (object $subscriber): array => [
                        'segment_id' => $current->id,
                        'subscriber_id' => (int) $subscriber->id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->values()->all());
                }, 'subscribers.id', 'id');

            // A sync is not an edit, so leave updated_at alone.
            Segment::withoutTimestamps(fn () => $current->forceFill(['rules_synced_at' => $now])->save());
            $segment->forceFill(['rules_synced_at' => $now])->syncOriginalAttribute('rules_synced_at');
        });
    }
}
