<?php

namespace Abduns\DbBackup\Events;

use Abduns\DbBackup\Models\DatabaseBackup;
use Abduns\DbBackup\RestorableBackup;

/**
 * A backup was loaded into a connection. $safetyBackup is the copy of the
 * database taken just before, or null when the database was empty or the
 * safety backup was skipped.
 */
class BackupRestored
{
    public function __construct(
        public RestorableBackup $backup,
        public string $connection,
        public ?DatabaseBackup $safetyBackup,
    ) {}
}
