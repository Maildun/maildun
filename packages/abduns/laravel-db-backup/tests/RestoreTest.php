<?php

use Abduns\DbBackup\BackupManager;
use Abduns\DbBackup\BackupRestorer;
use Abduns\DbBackup\Dumpers\Dumper;
use Abduns\DbBackup\Enums\BackupStatus;
use Abduns\DbBackup\Events\BackupRestored;
use Abduns\DbBackup\Exceptions\DumpFailed;
use Abduns\DbBackup\Exceptions\RestoreFailed;
use Abduns\DbBackup\Models\DatabaseBackup;
use Abduns\DbBackup\Restorers\MySqlRestorer;
use Abduns\DbBackup\Restorers\PostgresRestorer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('backups');

    $this->databasePath = sys_get_temp_dir().'/db-backup-restore-'.uniqid().'.sqlite';
    touch($this->databasePath);

    config(['database.connections.app' => [
        'driver' => 'sqlite',
        'database' => $this->databasePath,
        'prefix' => '',
        'foreign_key_constraints' => true,
    ]]);

    Schema::connection('app')->create('notes', fn ($table) => $table->string('body'));
    DB::connection('app')->table('notes')->insert(['body' => 'original']);
});

afterEach(function () {
    // Testbench rolls back migrations on the default connection after this hook.
    config(['database.default' => 'testing']);
    DB::purge('app');

    foreach (['', '-wal', '-shm', '.restoring'] as $suffix) {
        @unlink($this->databasePath.$suffix);
    }
});

test('the latest backup replaces the database, after a safety backup of what was there', function () {
    Event::fake([BackupRestored::class]);
    $backup = app(BackupManager::class)->run('app');

    DB::connection('app')->table('notes')->delete();
    DB::connection('app')->table('notes')->insert(['body' => 'changed later']);

    $this->travel(1)->minutes();

    $this->artisan('db-backup:restore', ['--latest' => true, '--connection' => 'app', '--force' => true])
        ->expectsOutputToContain('The database as it was before is saved as db-backups/app-')
        ->expectsOutputToContain('Restored "app" from '.$backup->file_name)
        ->assertSuccessful();

    expect(DB::connection('app')->table('notes')->pluck('body')->all())->toBe(['original']);

    $safety = DatabaseBackup::query()->where('label', 'pre-restore')->sole();
    expect($safety->path)->toEndWith('-pre-restore.sqlite.gz');
    Storage::disk('backups')->assertExists($safety->path);

    Event::assertDispatched(BackupRestored::class, fn (BackupRestored $event): bool => $event->connection === 'app'
        && $event->backup->path === $backup->path
        && $event->safetyBackup?->is($safety));
});

test('--latest skips pre-restore safety copies', function () {
    $regular = app(BackupManager::class)->run('app');
    $this->travel(1)->minutes();
    app(BackupManager::class)->run('app', label: 'pre-restore');

    expect(app(BackupRestorer::class)->latest('app')?->path)->toBe($regular->path)
        ->and(app(BackupRestorer::class)->available('app'))->toHaveCount(2);
});

test('a backup is found by its file name and the history it carries is repaired', function () {
    config(['database.default' => 'app']);
    $this->artisan('migrate', ['--database' => 'app', '--path' => realpath(__DIR__.'/../database/migrations'), '--realpath' => true]);

    $backup = app(BackupManager::class)->run();
    $this->travel(1)->minutes();

    $this->artisan('db-backup:restore', ['backup' => $backup->file_name, '--force' => true])->assertSuccessful();

    $restoredRow = DatabaseBackup::query()->whereKey($backup->id)->sole();
    expect($restoredRow->status)->toBe(BackupStatus::Succeeded)
        ->and($restoredRow->path)->toBe($backup->path)
        ->and(DatabaseBackup::query()->where('label', 'pre-restore')->where('status', BackupStatus::Succeeded)->count())->toBe(1);
});

test('nothing is restored without confirmation', function () {
    $backup = app(BackupManager::class)->run('app');
    DB::connection('app')->table('notes')->insert(['body' => 'keep']);

    $this->artisan('db-backup:restore', ['backup' => $backup->file_name, '--connection' => 'app'])
        ->expectsConfirmation('Restore it?', 'no')
        ->assertFailed();

    $this->artisan('db-backup:restore', ['backup' => $backup->file_name, '--connection' => 'app', '--no-interaction' => true])
        ->expectsOutputToContain('Pass --force to restore without confirmation.')
        ->assertFailed();

    expect(DB::connection('app')->table('notes')->count())->toBe(2)
        ->and(DatabaseBackup::query()->where('label', 'pre-restore')->exists())->toBeFalse();
});

test('a backup made for another database driver is refused', function () {
    Storage::disk('backups')->put('db-backups/app-2026-10-10-020000.dump', 'PGDMP');

    $this->artisan('db-backup:restore', ['backup' => 'app-2026-10-10-020000.dump', '--connection' => 'app', '--force' => true])
        ->expectsOutputToContain('app-2026-10-10-020000.dump is a .dump backup, but the "app" connection (sqlite) needs a .sqlite.gz backup.')
        ->assertFailed();

    expect(DB::connection('app')->table('notes')->pluck('body')->all())->toBe(['original']);
});

test('a missing backup is reported', function () {
    $this->artisan('db-backup:restore', ['backup' => 'app-2020-01-01-000000.sqlite.gz', '--connection' => 'app', '--force' => true])
        ->expectsOutputToContain('No backup named app-2020-01-01-000000.sqlite.gz was found on the "backups" disk.')
        ->assertFailed();
});

test('the restore stops when the safety backup fails', function () {
    $backup = app(BackupManager::class)->run('app');
    config(['db-backup.dumpers.sqlite' => FailingSqliteDumper::class]);
    DB::connection('app')->table('notes')->insert(['body' => 'keep']);

    expect(fn () => app(BackupRestorer::class)->restore(app(BackupRestorer::class)->find($backup->file_name), 'app'))
        ->toThrow(RestoreFailed::class, 'The safety backup failed, so nothing was restored: disk full');

    expect(DB::connection('app')->table('notes')->count())->toBe(2);
});

test('pg_restore loads the dump in one transaction with the password only in the environment', function () {
    Process::fake();

    (new PostgresRestorer)->restore('pgsql', [
        'host' => 'db.internal',
        'port' => 6543,
        'username' => 'app',
        'password' => 'secret',
        'database' => 'production',
    ], '/tmp/in.dump');

    Process::assertRan(fn ($process): bool => $process->command === [
        'pg_restore',
        '--single-transaction',
        '--exit-on-error',
        '--no-owner',
        '--no-acl',
        '--host=db.internal',
        '--port=6543',
        '--username=app',
        '--dbname=production',
        '/tmp/in.dump',
    ] && $process->environment === ['PGPASSWORD' => 'secret']);
});

test('the mysql client receives the unpacked dump on stdin and the password from an option file', function () {
    $received = null;
    Process::fake(function ($process) use (&$received) {
        $received = stream_get_contents($process->input);

        return Process::result();
    });
    $path = sys_get_temp_dir().'/db-backup-mysql-restore.sql.gz';
    file_put_contents($path, gzencode('INSERT INTO notes VALUES (1);'));

    (new MySqlRestorer)->restore('mysql', ['host' => 'db.internal', 'username' => 'app', 'password' => 'secret', 'database' => 'production'], $path);

    expect($received)->toBe('INSERT INTO notes VALUES (1);');
    Process::assertRan(fn ($process): bool => str_starts_with($process->command[1], '--defaults-extra-file=')
        && ! in_array('secret', $process->command, true)
        && end($process->command) === 'production');

    @unlink($path);
});

class FailingSqliteDumper implements Dumper
{
    public function dump(string $connection, array $config, string $path): void
    {
        throw new DumpFailed('disk full');
    }

    public function extension(): string
    {
        return 'sqlite.gz';
    }
}
