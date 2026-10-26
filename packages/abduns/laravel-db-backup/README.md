# Laravel DB Backup

Back up a Laravel database to any filesystem disk (AWS S3, Cloudflare R2, MinIO, local) on a schedule, with a history of every run, retention, and an alert when backups stop succeeding.

- PostgreSQL (`pg_dump` custom format), MySQL/MariaDB (`mysqldump`, gzipped) and SQLite (`VACUUM INTO`, gzipped).
- Every run is a `database_backups` row: queued, running, succeeded or failed, with size, error, and who started it.
- Streams the dump to the disk; nothing is held in memory.
- Retention by count and/or age; the newest successful backup is never pruned.
- One-command restore that works on an empty server, with a safety backup of the current database first.
- Events (`BackupSucceeded`, `BackupFailed`, `BackupIsStale`, `BackupRestored`) so your app decides how to alert.
- No UI: build the screen your app needs on top of the `DatabaseBackup` model.

## Requirements

- PHP 8.3+, Laravel 12 or 13
- `pg_dump` and `pg_restore` (PostgreSQL) or `mysqldump` and `mysql` (MySQL, MariaDB) on the server. For PostgreSQL, use the same major version as the server or newer.
- SQLite 3.27+ for SQLite databases
- For S3-compatible disks: `league/flysystem-aws-s3-v3`

## Installation

```bash
composer require abduns/laravel-db-backup
php artisan vendor:publish --tag=db-backup-config
php artisan vendor:publish --tag=db-backup-migrations
php artisan migrate
```

## Configuration

Point the package at a disk in `config/filesystems.php`. Keep backups in their own bucket, away from the server they protect. For Cloudflare R2:

```php
'backups' => [
    'driver' => 's3',
    'key' => env('R2_ACCESS_KEY_ID'),
    'secret' => env('R2_SECRET_ACCESS_KEY'),
    'region' => 'auto',
    'bucket' => env('BACKUP_R2_BUCKET'),
    'endpoint' => env('R2_ENDPOINT'),
],
```

Then set the environment:

```dotenv
DB_BACKUP_DISK=backups
DB_BACKUP_SCHEDULE=true
DB_BACKUP_TIME=02:00
DB_BACKUP_TIMEZONE=Asia/Jakarta
DB_BACKUP_KEEP_LAST=14
```

| Variable | Default | Purpose |
| --- | --- | --- |
| `DB_BACKUP_CONNECTION` | default connection | Connection to back up. |
| `DB_BACKUP_DISK` | `local` | Disk the backups are written to. |
| `DB_BACKUP_PATH` | `db-backups` | Folder on the disk. |
| `DB_BACKUP_SCHEDULE` | `false` | Let the package schedule the backup, prune and monitor commands. |
| `DB_BACKUP_TIME` | `02:00` | Daily backup time, 24-hour `HH:MM`. Pruning runs an hour later. |
| `DB_BACKUP_MONITOR_TIME` | `08:00` | Daily time of the staleness check. |
| `DB_BACKUP_TIMEZONE` | `app.timezone` | Timezone of the times above. |
| `DB_BACKUP_KEEP_LAST` | `14` | Successful backups to keep. Empty keeps all. |
| `DB_BACKUP_KEEP_DAYS` | — | Also delete backups older than this many days. |
| `DB_BACKUP_MAX_AGE_HOURS` | `26` | `db-backup:monitor` fails when no backup succeeded within this window. |
| `DB_BACKUP_TIMEOUT` | `3600` | Seconds a dump may run. |
| `DB_BACKUP_PG_DUMP`, `DB_BACKUP_MYSQLDUMP` | `pg_dump`, `mysqldump` | Backup binaries. Use `mariadb-dump` for MariaDB 11+. |
| `DB_BACKUP_PG_RESTORE`, `DB_BACKUP_MYSQL` | `pg_restore`, `mysql` | Restore binaries. Use `mariadb` for MariaDB 11+. |
| `DB_BACKUP_QUEUE_CONNECTION`, `DB_BACKUP_QUEUE` | defaults | Where queued backups run. |

Extra dump flags per driver (`options`) and the dumper class per driver (`dumpers`) are in `config/db-backup.php`. Implement `Abduns\DbBackup\Dumpers\Dumper` to support another driver.

## Scheduling

With `DB_BACKUP_SCHEDULE=true` the package registers, in `DB_BACKUP_TIMEZONE`:

| Command | When |
| --- | --- |
| `db-backup:run` | daily at `DB_BACKUP_TIME` |
| `db-backup:prune` | an hour after `DB_BACKUP_TIME` |
| `db-backup:monitor` | daily at `DB_BACKUP_MONITOR_TIME` |

Laravel's scheduler must run every minute (`php artisan schedule:run`). Check the result with `php artisan schedule:list`, which shows times converted to `app.timezone`.

To schedule the commands yourself instead, leave `DB_BACKUP_SCHEDULE=false` and add them to `routes/console.php`.

## Usage

```bash
php artisan db-backup:run                     # back up now
php artisan db-backup:run --connection=mysql  # a specific connection
php artisan db-backup:prune                   # apply retention
php artisan db-backup:monitor                 # exit 1 and fire BackupIsStale when backups are overdue
```

From code:

```php
use Abduns\DbBackup\BackupManager;

$backup = app(BackupManager::class)->run();                          // run now
$backup = app(BackupManager::class)->queue(triggeredBy: $request->user()); // run on the queue

$backup->status;      // BackupStatus::Pending|Running|Succeeded|Failed
$backup->path;        // path on $backup->disk
$backup->error;       // why it failed
$backup->triggeredBy; // the model that asked for it, null for scheduled runs
```

Pass a label to keep a backup apart from the regular ones: `run(label: 'before-upgrade')` writes `…-before-upgrade.dump`, which `--latest` never restores.

Only one backup of a connection runs at a time; a second one fails with a clear error. Runs that never finished (a killed worker, a job nobody picked up) are marked failed when the next backup starts.

## Events

| Event | Fired when |
| --- | --- |
| `Abduns\DbBackup\Events\BackupSucceeded` | a backup is on the disk (`$event->backup`) |
| `Abduns\DbBackup\Events\BackupFailed` | a backup failed (`$event->backup->error`) |
| `Abduns\DbBackup\Events\BackupIsStale` | `db-backup:monitor` found no recent success (`$event->connection`, `$event->latest`, `$event->maxAgeHours`) |
| `Abduns\DbBackup\Events\BackupRestored` | a backup was restored (`$event->backup`, `$event->connection`, `$event->safetyBackup`) |

Listen to them to send mail, Slack messages, or anything else.

## Restoring

```bash
php artisan db-backup:restore                    # pick from the backups on the disk
php artisan db-backup:restore --latest           # the newest backup of the connection
php artisan db-backup:restore pgsql-2026-10-10-020000.dump
php artisan db-backup:restore --latest --force --migrate   # unattended, then migrate
```

| Option | Purpose |
| --- | --- |
| `--latest` | Restore the newest regular backup. Labelled backups (such as safety copies) are skipped. |
| `--connection=` | Connection to restore into. Defaults to `DB_BACKUP_CONNECTION` or the default connection. |
| `--force` | Do not ask for confirmation. Required when the command is not interactive. |
| `--migrate` | Run `migrate --force` afterwards, for code newer than the backup. |
| `--no-safety-backup` | Do not back up the current database first. |

How it works:

1. Backups are found by **listing the disk**, not the `database_backups` table, so a new server with an empty database can restore.
2. The file must match the connection's driver; a `.dump` is never loaded into MySQL.
3. If the database has tables, a **safety backup** is taken first, named `…-pre-restore.<ext>`. If it fails, nothing is restored.
4. PostgreSQL and MySQL: every table, view and (PostgreSQL) type is dropped, then the dump is loaded. `pg_restore` runs with `--single-transaction --exit-on-error`. SQLite: the database file is swapped for the restored copy.
5. The history table is repaired: the restored backup's own row, which still said "running" when it was dumped, is marked succeeded, and the safety backup's row is added back.
6. `Abduns\DbBackup\Events\BackupRestored` is fired.

From code:

```php
use Abduns\DbBackup\BackupRestorer;

$restorer = app(BackupRestorer::class);
$safetyBackup = $restorer->restore($restorer->latest(), takeSafetyBackup: true);
```

Restoring needs `pg_restore` (PostgreSQL) or the `mysql` client (MySQL, MariaDB), set with `DB_BACKUP_PG_RESTORE` and `DB_BACKUP_MYSQL` when they are not on `PATH`. Configure other drivers under `restorers` by implementing `Abduns\DbBackup\Restorers\Restorer`.

### Moving to a new server

1. Deploy the app on the new server with the same `APP_KEY` and disk credentials, and an empty database.
2. On the old server: `php artisan down && php artisan db-backup:run`.
3. On the new server: `php artisan db-backup:restore --latest --force --migrate`.
4. Point DNS at the new server and stop the old scheduler.

Files outside the database (uploads on a local disk, keys in `storage/`) are not part of a backup; copy them separately.

## Testing

```bash
composer install
vendor/bin/pest
```

## License

MIT
