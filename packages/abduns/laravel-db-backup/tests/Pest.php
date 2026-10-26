<?php

use Abduns\DbBackup\Enums\BackupStatus;
use Abduns\DbBackup\Models\DatabaseBackup;
use Abduns\DbBackup\Tests\TestCase;
use Illuminate\Support\Facades\Storage;

pest()->extend(TestCase::class)->in(__DIR__);

/**
 * A finished backup row, with its file on the disk when it succeeded.
 *
 * @param  array<string, mixed>  $attributes
 */
function backupRecord(array $attributes = []): DatabaseBackup
{
    $backup = new DatabaseBackup;
    $backup->forceFill([
        'status' => BackupStatus::Succeeded,
        'connection' => 'testing',
        'driver' => 'sqlite',
        'disk' => 'backups',
        'started_at' => now(),
        'finished_at' => now(),
        ...$attributes,
    ]);

    if ($backup->status === BackupStatus::Succeeded && ! array_key_exists('path', $attributes)) {
        $backup->path = 'db-backups/'.fake()->uuid().'.sqlite.gz';
        $backup->file_name = basename($backup->path);
        $backup->size = 3;
        Storage::disk('backups')->put($backup->path, 'gz!');
    }

    $backup->save();

    return $backup;
}
