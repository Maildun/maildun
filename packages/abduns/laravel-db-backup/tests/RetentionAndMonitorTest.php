<?php

use Abduns\DbBackup\Enums\BackupStatus;
use Abduns\DbBackup\Events\BackupIsStale;
use Abduns\DbBackup\Models\DatabaseBackup;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('backups');
});

test('pruning keeps the newest backups and deletes older files and failed runs', function () {
    config(['db-backup.retention.keep_last' => 2, 'db-backup.retention.keep_days' => null]);

    $oldestFailure = backupRecord(['status' => BackupStatus::Failed, 'path' => null, 'created_at' => now()->subDays(5)]);
    $expired = [backupRecord(['created_at' => now()->subDays(4)]), backupRecord(['created_at' => now()->subDays(3)])];
    $kept = [backupRecord(['created_at' => now()->subDays(2)]), backupRecord(['created_at' => now()->subDay()])];
    $recentFailure = backupRecord(['status' => BackupStatus::Failed, 'path' => null, 'created_at' => now()->subHours(36)]);

    $this->artisan('db-backup:prune')
        ->expectsOutputToContain('Pruned 3 backups.')
        ->assertSuccessful();

    expect(DatabaseBackup::query()->pluck('id')->sort()->values()->all())
        ->toBe(collect([...$kept, $recentFailure])->pluck('id')->sort()->values()->all());

    foreach ($expired as $backup) {
        Storage::disk('backups')->assertMissing($backup->path);
    }

    foreach ($kept as $backup) {
        Storage::disk('backups')->assertExists($backup->path);
    }

    expect($oldestFailure->fresh())->toBeNull();
});

test('pruning by age never deletes the newest successful backup', function () {
    config(['db-backup.retention.keep_last' => null, 'db-backup.retention.keep_days' => 7]);

    backupRecord(['created_at' => now()->subDays(20)]);
    $newest = backupRecord(['created_at' => now()->subDays(10)]);

    $this->artisan('db-backup:prune')->assertSuccessful();

    expect(DatabaseBackup::query()->pluck('id')->all())->toBe([$newest->id]);
});

test('the monitor passes when a backup succeeded recently', function () {
    Event::fake([BackupIsStale::class]);
    backupRecord(['finished_at' => now()->subHours(3)]);

    $this->artisan('db-backup:monitor')->assertSuccessful();

    Event::assertNotDispatched(BackupIsStale::class);
});

test('the monitor fails and announces a stale backup', function (?int $hoursAgo) {
    Event::fake([BackupIsStale::class]);
    config(['db-backup.max_age_hours' => 26]);

    if ($hoursAgo !== null) {
        backupRecord(['finished_at' => now()->subHours($hoursAgo)]);
    }

    backupRecord(['status' => BackupStatus::Failed, 'path' => null, 'finished_at' => now()]);

    $this->artisan('db-backup:monitor')
        ->expectsOutputToContain('No backup succeeded in the last 26 hours.')
        ->assertFailed();

    Event::assertDispatched(BackupIsStale::class, fn (BackupIsStale $event): bool => $event->connection === 'testing'
        && $event->maxAgeHours === 26
        && ($hoursAgo === null) === ($event->latest === null));
})->with([
    'no backup ever' => [null],
    'only an old backup' => [30],
]);

test('the run command reports where the backup went', function () {
    $this->artisan('db-backup:run')
        ->expectsOutputToContain('Backed up "testing" to backups:db-backups/testing-')
        ->assertSuccessful();
});

test('the run command fails when the backup fails', function () {
    config(['db-backup.dumpers.sqlite' => null]);

    $this->artisan('db-backup:run')
        ->expectsOutputToContain('Backup of "testing" failed')
        ->assertFailed();
});
