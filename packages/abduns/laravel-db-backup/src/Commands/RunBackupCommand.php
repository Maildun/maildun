<?php

namespace Abduns\DbBackup\Commands;

use Abduns\DbBackup\BackupManager;
use Illuminate\Console\Command;

class RunBackupCommand extends Command
{
    protected $signature = 'db-backup:run {--connection= : The database connection to back up}';

    protected $description = 'Back up the database to the configured disk';

    public function handle(BackupManager $backups): int
    {
        $backup = $backups->run($this->option('connection') ?: null);

        if (! $backup->isSucceeded()) {
            $this->components->error(sprintf('Backup of "%s" failed: %s', $backup->connection, $backup->error));

            return self::FAILURE;
        }

        $this->components->info(sprintf(
            'Backed up "%s" to %s:%s (%s bytes).',
            $backup->connection,
            $backup->disk,
            $backup->path,
            number_format((int) $backup->size),
        ));

        return self::SUCCESS;
    }
}
