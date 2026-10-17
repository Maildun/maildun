<?php

namespace App\Jobs;

use App\Models\Subscriber;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Subscribers keep custom field values in attribute_values keyed by the
 * attribute key. Renaming a key moves each stored value to the new key, and
 * deleting the attribute removes it, so no value is orphaned under a key
 * nothing reads any more.
 *
 * A value already stored under the new key (a signup that arrived after the
 * rename) wins over the one being moved.
 */
class MoveAudienceAttributeValues implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public function __construct(
        public int $audienceId,
        public string $fromKey,
        public ?string $toKey = null,
    ) {}

    public function handle(): void
    {
        Subscriber::query()
            ->where('audience_id', $this->audienceId)
            ->whereNotNull('attribute_values')
            ->select(['id', 'attribute_values'])
            ->chunkById(500, function ($subscribers): void {
                foreach ($subscribers as $subscriber) {
                    $values = $subscriber->attribute_values ?? [];

                    if (! array_key_exists($this->fromKey, $values)) {
                        continue;
                    }

                    if ($this->toKey !== null && ! array_key_exists($this->toKey, $values)) {
                        $values[$this->toKey] = $values[$this->fromKey];
                    }

                    unset($values[$this->fromKey]);

                    $subscriber->forceFill(['attribute_values' => $values])->saveQuietly();
                }
            });
    }
}
