<?php

namespace Abduns\DbBackup;

use Abduns\DbBackup\Dumpers\Dumper;
use Abduns\DbBackup\Enums\BackupStatus;
use Abduns\DbBackup\Events\BackupFailed;
use Abduns\DbBackup\Events\BackupIsStale;
use Abduns\DbBackup\Events\BackupSucceeded;
use Abduns\DbBackup\Exceptions\DumpFailed;
use Abduns\DbBackup\Jobs\RunDatabaseBackup;
use Abduns\DbBackup\Models\DatabaseBackup;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Runs, records, prunes and checks database backups.
 *
 * Every run is a DatabaseBackup row first, so a backup that is queued, running,
 * or failed is visible just like a finished one.
 */
class BackupManager
{
    public function __construct(private Container $container) {}

    /**
     * Record a backup and run it on the queue.
     */
    public function queue(?string $connection = null, ?Model $triggeredBy = null): DatabaseBackup
    {
        $backup = $this->createRecord($connection, $triggeredBy);

        $this->container->make(Dispatcher::class)->dispatch(
            (new RunDatabaseBackup($backup))
                ->onConnection(config('db-backup.queue.connection'))
                ->onQueue(config('db-backup.queue.name')),
        );

        return $backup;
    }

    /**
     * Record a backup and run it now. A label (lowercase letters, digits and
     * dashes) is added to the file name and keeps the backup out of
     * `db-backup:restore --latest`, as the pre-restore safety backup does.
     */
    public function run(?string $connection = null, ?Model $triggeredBy = null, ?string $label = null): DatabaseBackup
    {
        return $this->perform($this->createRecord($connection, $triggeredBy, $label));
    }

    /**
     * Dump the database, upload it to the disk, and record the outcome. A
     * failure is recorded on the row and announced with BackupFailed rather
     * than thrown. A backup that already started is left alone.
     */
    public function perform(DatabaseBackup $backup): DatabaseBackup
    {
        if ($backup->status !== BackupStatus::Pending) {
            return $backup;
        }

        $this->failAbandoned($backup);

        $lock = Cache::lock('db-backup:'.$backup->connection, $this->timeout() + 300);

        if (! $lock->get()) {
            return $this->fail($backup, 'Another backup of this connection is still running.');
        }

        $localPath = null;

        try {
            $config = DB::connection($backup->connection)->getConfig();
            $driver = (string) ($config['driver'] ?? '');

            $backup->forceFill([
                'status' => BackupStatus::Running,
                'driver' => $driver,
                'started_at' => now(),
            ])->save();

            $dumper = $this->dumperFor($backup->connection, $driver);
            $directory = (string) config('db-backup.temporary_directory');
            File::ensureDirectoryExists($directory);
            $localPath = $directory.'/'.$backup->uuid.'.'.$dumper->extension();

            $dumper->dump($backup->connection, $config, $localPath);

            $fileName = sprintf(
                '%s-%s%s.%s',
                $backup->connection,
                $backup->started_at->format('Y-m-d-His'),
                $backup->label === null ? '' : '-'.$backup->label,
                $dumper->extension(),
            );
            $path = ltrim(trim((string) config('db-backup.path'), '/').'/'.$fileName, '/');

            $this->upload($backup->disk, $path, $localPath);

            $backup->forceFill([
                'status' => BackupStatus::Succeeded,
                'path' => $path,
                'file_name' => $fileName,
                'size' => filesize($localPath) ?: 0,
                'finished_at' => now(),
            ])->save();

            event(new BackupSucceeded($backup));
        } catch (Throwable $exception) {
            report($exception);

            $this->fail($backup, $exception->getMessage());
        } finally {
            if ($localPath !== null) {
                @unlink($localPath);
            }

            $lock->release();
        }

        return $backup;
    }

    /**
     * Mark an unfinished backup as failed and announce it.
     */
    public function fail(DatabaseBackup $backup, string $reason): DatabaseBackup
    {
        $backup->forceFill([
            'status' => BackupStatus::Failed,
            'error' => Str::limit($reason, 2000),
            'started_at' => $backup->started_at ?? now(),
            'finished_at' => now(),
        ])->save();

        event(new BackupFailed($backup));

        return $backup;
    }

    /**
     * Delete backups the retention rules no longer keep, and failed runs older
     * than the oldest backup still kept. Returns how many rows were removed.
     */
    public function prune(): int
    {
        $keepLast = $this->optionalLimit(config('db-backup.retention.keep_last'));
        $keepDays = $this->optionalLimit(config('db-backup.retention.keep_days'));
        $removed = 0;

        foreach (DatabaseBackup::query()->distinct()->pluck('connection') as $connection) {
            $succeeded = DatabaseBackup::query()
                ->where('connection', $connection)
                ->succeeded()
                ->latest('created_at')
                ->latest('id')
                ->get();

            // Rank 0 is the newest successful backup, which is always kept.
            $expired = $succeeded->slice(1)->filter(
                fn (DatabaseBackup $backup, int $rank): bool => ($keepLast !== null && $rank >= $keepLast)
                    || ($keepDays !== null && $backup->created_at->lt(now()->subDays($keepDays))),
            );

            foreach ($expired as $backup) {
                if ($this->deleteFile($backup)) {
                    $backup->delete();
                    $removed++;
                }
            }

            $oldestKept = DatabaseBackup::query()
                ->where('connection', $connection)
                ->succeeded()
                ->oldest('created_at')
                ->first();

            if ($oldestKept !== null) {
                $removed += DatabaseBackup::query()
                    ->where('connection', $connection)
                    ->where('status', BackupStatus::Failed)
                    ->where('created_at', '<', $oldestKept->created_at)
                    ->delete();
            }
        }

        return $removed;
    }

    /**
     * Whether a backup of the connection succeeded within the allowed age.
     * Fires BackupIsStale when none did.
     */
    public function isHealthy(?string $connection = null): bool
    {
        $connection = $this->connectionName($connection);
        $maxAgeHours = (int) config('db-backup.max_age_hours', 26);

        $latest = DatabaseBackup::query()
            ->where('connection', $connection)
            ->succeeded()
            ->latest('finished_at')
            ->first();

        if ($latest?->finished_at !== null && $latest->finished_at->gt(now()->subHours($maxAgeHours))) {
            return true;
        }

        event(new BackupIsStale($connection, $latest, $maxAgeHours));

        return false;
    }

    /**
     * The HH:MM the scheduled backup runs at, in scheduleTimezone().
     */
    public function scheduleTime(): string
    {
        return (string) config('db-backup.schedule.time', '02:00');
    }

    /**
     * The timezone scheduled backups run in: db-backup.schedule.timezone, or
     * the app timezone when that is not set.
     */
    public function scheduleTimezone(): string
    {
        return (string) (config('db-backup.schedule.timezone') ?: config('app.timezone', 'UTC'));
    }

    private function createRecord(?string $connection, ?Model $triggeredBy, ?string $label = null): DatabaseBackup
    {
        if ($label !== null && preg_match('/^[a-z][a-z0-9-]*$/', $label) !== 1) {
            throw new InvalidArgumentException('A backup label may only contain lowercase letters, digits and dashes.');
        }

        $backup = new DatabaseBackup;
        $backup->forceFill([
            'status' => BackupStatus::Pending,
            'connection' => $this->connectionName($connection),
            'label' => $label,
            'disk' => (string) config('db-backup.disk'),
        ]);

        if ($triggeredBy !== null) {
            $backup->triggeredBy()->associate($triggeredBy);
        }

        $backup->save();

        return $backup;
    }

    /**
     * Fail earlier runs of the same connection that never finished, such as a
     * worker killed mid-dump or a queued job that was never picked up.
     */
    private function failAbandoned(DatabaseBackup $current): void
    {
        DatabaseBackup::query()
            ->where('connection', $current->connection)
            ->whereKeyNot($current->getKey())
            ->unfinished()
            ->where('created_at', '<', now()->subSeconds($this->timeout() + 3600))
            ->get()
            ->each(fn (DatabaseBackup $backup): DatabaseBackup => $this->fail($backup, 'The backup did not finish.'));
    }

    /**
     * The dumper configured for a database driver.
     */
    public function dumperFor(string $connection, string $driver): Dumper
    {
        $class = config('db-backup.dumpers.'.$driver);

        if (! is_string($class) || $class === '') {
            throw DumpFailed::unsupportedDriver($connection, $driver);
        }

        return $this->container->make($class);
    }

    private function upload(string $disk, string $path, string $localPath): void
    {
        $stream = fopen($localPath, 'rb');

        if ($stream === false) {
            throw new RuntimeException('Could not read the dump file.');
        }

        try {
            $written = Storage::disk($disk)->writeStream($path, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        if ($written === false) {
            Storage::disk($disk)->delete($path);

            throw new RuntimeException(sprintf('Could not write the backup to the "%s" disk.', $disk));
        }
    }

    /**
     * Delete the backup's file, reporting whether it is gone.
     */
    private function deleteFile(DatabaseBackup $backup): bool
    {
        if ($backup->path === null) {
            return true;
        }

        try {
            $disk = Storage::disk($backup->disk);
            $disk->delete($backup->path);

            return ! $disk->exists($backup->path);
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }

    /**
     * The connection to use: the one given, db-backup.connection, or the default.
     */
    public function connectionName(?string $connection = null): string
    {
        return $connection ?? config('db-backup.connection') ?? (string) config('database.default');
    }

    private function timeout(): int
    {
        return (int) config('db-backup.timeout', 3600);
    }

    private function optionalLimit(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : max(0, (int) $value);
    }
}
