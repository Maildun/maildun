<?php

namespace Abduns\DbBackup\Events;

use Abduns\DbBackup\Models\DatabaseBackup;
use Illuminate\Queue\SerializesModels;

/**
 * No successful backup of the connection finished within the allowed age.
 * $latest is the newest successful backup, or null when there has never been one.
 */
class BackupIsStale
{
    use SerializesModels;

    public function __construct(
        public string $connection,
        public ?DatabaseBackup $latest,
        public int $maxAgeHours,
    ) {}
}
