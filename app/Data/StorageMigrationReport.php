<?php

namespace App\Data;

readonly class StorageMigrationReport
{
    /**
     * @param  array<string, int>  $copiedByDisk
     * @param  array<string, int>  $skippedByDisk
     * @param  list<string>  $failures
     */
    public function __construct(
        public array $copiedByDisk = [],
        public array $skippedByDisk = [],
        public array $failures = [],
        public int $repointedAttachments = 0,
        public int $repointedMedia = 0,
        public int $repointedSubscribeForms = 0,
    ) {
        //
    }

    public function copied(): int
    {
        return array_sum($this->copiedByDisk);
    }

    public function skipped(): int
    {
        return array_sum($this->skippedByDisk);
    }

    public function failed(): bool
    {
        return $this->failures !== [];
    }
}
