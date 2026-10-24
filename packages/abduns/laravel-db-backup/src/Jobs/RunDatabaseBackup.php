<?php

namespace Abduns\DbBackup\Jobs;

use Abduns\DbBackup\BackupManager;
use Abduns\DbBackup\Models\DatabaseBackup;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Runs a queued backup once. A retry would start a second dump, so a failed
 * or timed-out attempt is recorded on the row instead.
 */
class RunDatabaseBackup implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout;

    public bool $failOnTimeout = true;

    public function __construct(public DatabaseBackup $backup)
    {
        $this->timeout = (int) config('db-backup.timeout', 3600) + 300;
    }

    public function handle(BackupManager $backups): void
    {
        $backups->perform($this->backup);
    }

    public function failed(?Throwable $exception): void
    {
        $backup = $this->backup->fresh();

        if ($backup !== null && ! $backup->status->isFinished()) {
            app(BackupManager::class)->fail($backup, $exception?->getMessage() ?? 'The backup job failed.');
        }
    }
}
