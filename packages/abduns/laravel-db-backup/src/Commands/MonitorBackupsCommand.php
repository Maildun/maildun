<?php

namespace Abduns\DbBackup\Commands;

use Abduns\DbBackup\BackupManager;
use Illuminate\Console\Command;

class MonitorBackupsCommand extends Command
{
    protected $signature = 'db-backup:monitor {--connection= : The database connection to check}';

    protected $description = 'Check that a recent database backup succeeded, firing BackupIsStale when none did';

    public function handle(BackupManager $backups): int
    {
        if (! $backups->isHealthy($this->option('connection') ?: null)) {
            $this->components->error(sprintf(
                'No backup succeeded in the last %d hours.',
                (int) config('db-backup.max_age_hours', 26),
            ));

            return self::FAILURE;
        }

        $this->components->info('The latest backup is recent.');

        return self::SUCCESS;
    }
}
