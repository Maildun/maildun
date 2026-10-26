<?php

namespace Abduns\DbBackup\Commands;

use Abduns\DbBackup\BackupManager;
use Illuminate\Console\Command;

class PruneBackupsCommand extends Command
{
    protected $signature = 'db-backup:prune';

    protected $description = 'Delete database backups the retention rules no longer keep';

    public function handle(BackupManager $backups): int
    {
        $removed = $backups->prune();

        $this->components->info(sprintf('Pruned %d %s.', $removed, $removed === 1 ? 'backup' : 'backups'));

        return self::SUCCESS;
    }
}
