<?php

namespace App\Jobs;

use App\Models\AudienceAttribute;
use App\Models\Subscriber;
use Illuminate\Contracts\Queue\ShouldQueue;
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
 * first. Each row is re-read under a lock, and a value already stored under
 * the destination (a signup that arrived after the rename) wins.
 */
class MoveAudienceAttributeValues implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    /**
     * @param  int|null  $attributeId  The renamed attribute; null when it was deleted.
     */
    public function __construct(
        public int $audienceId,
        public string $fromKey,
        public ?int $attributeId = null,
    ) {}

    public function handle(): void
    {
        $toKey = $this->attributeId === null
            ? null
            : AudienceAttribute::query()->whereKey($this->attributeId)->value('key');

        if ($toKey === $this->fromKey) {
            return;
        }

        Subscriber::query()
            ->where('audience_id', $this->audienceId)
            ->whereNotNull('attribute_values')
            ->select('id')
            ->chunkById(500, function ($subscribers) use ($toKey): void {
                foreach ($subscribers as $subscriber) {
                    DB::transaction(fn () => $this->move($subscriber->id, $toKey));
                }
            });
    }

    private function move(int $subscriberId, ?string $toKey): void
    {
        $subscriber = Subscriber::query()->whereKey($subscriberId)->lockForUpdate()->first(['id', 'attribute_values']);

        if ($subscriber === null) {
            return;
        }

        $values = $subscriber->attribute_values;

        if (! array_key_exists($this->fromKey, $values)) {
            return;
        }

        if ($toKey !== null && ! array_key_exists($toKey, $values)) {
            $values[$toKey] = $values[$this->fromKey];
        }

        unset($values[$this->fromKey]);

        $subscriber->forceFill(['attribute_values' => $values])->saveQuietly();
    }
}
