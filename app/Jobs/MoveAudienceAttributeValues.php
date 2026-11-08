<?php

namespace App\Jobs;

use App\Models\AudienceAttribute;
use App\Models\AudienceAttributeMove;
use App\Models\Subscriber;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

/**
 * Subscribers keep custom field values in attribute_values keyed by the
 * attribute key. Renaming a key moves each stored value to the attribute's
 * key, and deleting the attribute removes it, so no value is orphaned under
 * a key nothing reads any more.
 *
 * The destination is the attribute's key when the job runs, not when it was
 * queued, so two quick renames land on the final key whichever job runs
 * first. A value already stored under the destination (a signup that arrived
 * after the rename) wins.
 *
 * Values move one chunk of members per UPDATE statement, so each row changes
 * atomically against concurrent writes. The timeout stays under the Redis
 * queue's retry_after (90s) so a slow run is never handed to a second worker
 * while it is still going. Each chunk commits on its own and only rows still
 * holding the old key are read, so a run that times out resumes where it
 * stopped; retryUntil gives a large audience as many runs as it needs.
 *
 * The AudienceAttributeMove row holds the old key until the job finishes, so
 * no new field can take it in the meantime.
 */
class MoveAudienceAttributeValues implements ShouldQueue
{
    use Queueable;

    /**
     * How long the job keeps retrying. A pending move older than this has no
     * job left to finish it and stops holding its key.
     */
    public const int RETRY_WINDOW_HOURS = 24;

    /** Members rewritten per UPDATE statement. */
    private const int CHUNK_SIZE = 500;

    public int $timeout = 60;

    /** A query that keeps failing is a bug, not a slow audience. */
    public int $maxExceptions = 5;

    /** @var list<int> */
    public array $backoff = [30, 120, 300];

    /**
     * @param  int|null  $attributeId  The renamed attribute; null when it was deleted.
     * @param  int|null  $moveId  The AudienceAttributeMove holding the key until this job finishes.
     */
    public function __construct(
        public int $audienceId,
        public string $fromKey,
        public ?int $attributeId = null,
        public ?int $moveId = null,
    ) {}

    public function retryUntil(): DateTimeInterface
    {
        return now()->addHours(self::RETRY_WINDOW_HOURS);
    }

    public function handle(): void
    {
        $toKey = $this->attributeId === null
            ? null
            : AudienceAttribute::query()->whereKey($this->attributeId)->value('key');

        if ($toKey !== $this->fromKey) {
            Subscriber::query()
                ->where('audience_id', $this->audienceId)
                ->whereJsonContainsKey('attribute_values->'.$this->fromKey)
                ->select('id')
                ->chunkById(self::CHUNK_SIZE, function (Collection $subscribers) use ($toKey): void {
                    $this->moveValues($subscribers->modelKeys(), $toKey);
                });
        }

        if ($this->moveId !== null) {
            AudienceAttributeMove::query()->whereKey($this->moveId)->delete();
        }
    }

    /**
     * Rewrite one chunk of members in a single statement: copy the value to
     * the destination unless one is already there, then drop the old key.
     *
     * @param  array<int, int>  $subscriberIds
     */
    private function moveValues(array $subscriberIds, ?string $toKey): void
    {
        $isPostgres = DB::connection()->getDriverName() === 'pgsql';
        $ids = implode(', ', array_fill(0, count($subscriberIds), '?'));

        // Postgres keeps the column as json, so the edit goes through jsonb. A
        // missing key reads as SQL NULL; a key holding JSON null does not.
        [$values, $bindings] = match (true) {
            $isPostgres && $toKey === null => [
                '(attribute_values::jsonb - CAST(? AS text))::json',
                [$this->fromKey],
            ],
            $isPostgres => [
                '((CASE WHEN (attribute_values::jsonb -> CAST(? AS text)) IS NULL'
                .' THEN attribute_values::jsonb || jsonb_build_object(CAST(? AS text), attribute_values::jsonb -> CAST(? AS text))'
                .' ELSE attribute_values::jsonb END) - CAST(? AS text))::json',
                [$toKey, $toKey, $this->fromKey, $this->fromKey],
            ],
            $toKey === null => [
                'json_remove(attribute_values, ?)',
                [$this->path($this->fromKey)],
            ],
            default => [
                'json_remove(CASE WHEN json_type(attribute_values, ?) IS NULL'
                .' THEN json_set(attribute_values, ?, json_extract(attribute_values, ?))'
                .' ELSE attribute_values END, ?)',
                [$this->path($toKey), $this->path($toKey), $this->path($this->fromKey), $this->path($this->fromKey)],
            ],
        };

        $holdsOldKey = $isPostgres
            ? '(attribute_values::jsonb -> CAST(? AS text)) IS NOT NULL'
            : 'json_type(attribute_values, ?) IS NOT NULL';

        DB::update(
            "update subscribers set attribute_values = {$values}, updated_at = ? where id in ({$ids}) and {$holdsOldKey}",
            [
                ...$bindings,
                now(),
                ...array_values($subscriberIds),
                $isPostgres ? $this->fromKey : $this->path($this->fromKey),
            ],
        );
    }

    /**
     * A SQLite JSON path naming one top-level key.
     */
    private function path(string $key): string
    {
        return '$."'.$key.'"';
    }
}
