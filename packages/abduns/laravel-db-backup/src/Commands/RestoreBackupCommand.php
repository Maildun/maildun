<?php

namespace Abduns\DbBackup\Commands;

use Abduns\DbBackup\BackupRestorer;
use Abduns\DbBackup\Exceptions\RestoreFailed;
use Abduns\DbBackup\RestorableBackup;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

use function Laravel\Prompts\select;

class RestoreBackupCommand extends Command
{
    protected $signature = 'db-backup:restore
        {backup? : File name or disk path of the backup to restore}
        {--latest : Restore the newest backup of the connection}
        {--connection= : The database connection to restore into}
        {--migrate : Run migrations after restoring}
        {--no-safety-backup : Do not back up the current database first}
        {--force : Restore without asking for confirmation}';

    protected $description = 'Replace the database with a backup from the configured disk';

    public function handle(BackupRestorer $restorer): int
    {
        $connection = $restorer->connectionName($this->option('connection') ?: null);
        $backup = $this->chooseBackup($restorer, $connection);

        if ($backup === null) {
            return self::FAILURE;
        }

        $config = DB::connection($connection)->getConfig();

        $this->components->twoColumnDetail('Backup', $backup->fileName);
        $this->components->twoColumnDetail('Taken at', $backup->takenAt->format('Y-m-d H:i:s T'));
        $this->components->twoColumnDetail('Restore into', sprintf(
            '%s (%s, %s%s)',
            $connection,
            $config['driver'] ?? '?',
            $config['database'] ?? '?',
            isset($config['host']) ? ' on '.Arr::first(Arr::wrap($config['host'])) : '',
        ));
        $this->newLine();

        if (! $this->confirmed($connection)) {
            return self::FAILURE;
        }

        try {
            $safetyBackup = null;

            $this->components->task('Restoring '.$backup->fileName, function () use ($restorer, $backup, $connection, &$safetyBackup): void {
                $safetyBackup = $restorer->restore($backup, $connection, ! $this->option('no-safety-backup'));
            });
        } catch (RestoreFailed $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($safetyBackup !== null) {
            $this->components->info(sprintf('The database as it was before is saved as %s.', $safetyBackup->path));
        }

        if ($this->option('migrate')) {
            $this->call('migrate', ['--database' => $connection, '--force' => true]);
        }

        $this->components->info(sprintf('Restored "%s" from %s.', $connection, $backup->fileName));

        return self::SUCCESS;
    }

    private function chooseBackup(BackupRestorer $restorer, string $connection): ?RestorableBackup
    {
        if (filled($this->argument('backup'))) {
            $backup = $restorer->find((string) $this->argument('backup'));

            if ($backup === null) {
                $this->components->error(sprintf('No backup named %s was found on the "%s" disk.', $this->argument('backup'), config('db-backup.disk')));
            }

            return $backup;
        }

        if ($this->option('latest')) {
            $backup = $restorer->latest($connection);

            if ($backup === null) {
                $this->components->error(sprintf('No backups of "%s" were found on the "%s" disk.', $connection, config('db-backup.disk')));
            }

            return $backup;
        }

        if (! $this->input->isInteractive()) {
            $this->components->error('Pass a backup file name or --latest.');

            return null;
        }

        $available = $restorer->available($connection)->take(20);

        if ($available->isEmpty()) {
            $this->components->error(sprintf('No backups of "%s" were found on the "%s" disk.', $connection, config('db-backup.disk')));

            return null;
        }

        $path = select(
            label: 'Which backup should be restored?',
            options: $available->mapWithKeys(fn (RestorableBackup $backup): array => [
                $backup->path => $backup->takenAt->format('Y-m-d H:i').($backup->label === null ? '' : ' ('.$backup->label.')').'  '.$backup->fileName,
            ])->all(),
            scroll: 10,
        );

        return $available->firstWhere('path', $path);
    }

    private function confirmed(string $connection): bool
    {
        if ($this->option('force')) {
            return true;
        }

        if (! $this->input->isInteractive()) {
            $this->components->error('Restoring replaces the whole database. Pass --force to restore without confirmation.');

            return false;
        }

        $this->components->warn(sprintf('Everything in the "%s" database will be replaced by this backup.', $connection));

        return $this->components->confirm('Restore it?', false);
    }
}
