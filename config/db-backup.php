<?php

use Abduns\DbBackup\Dumpers\MySqlDumper;
use Abduns\DbBackup\Dumpers\PostgresDumper;
use Abduns\DbBackup\Dumpers\SqliteDumper;
use Abduns\DbBackup\Restorers\MySqlRestorer;
use Abduns\DbBackup\Restorers\PostgresRestorer;
use Abduns\DbBackup\Restorers\SqliteRestorer;

return [

    /*
    |--------------------------------------------------------------------------
    | Database Connection
    |--------------------------------------------------------------------------
    |
    | The connection to back up when none is given. Null uses the application's
    | default connection.
    |
    */

    'connection' => env('DB_BACKUP_CONNECTION'),

    /*
    |--------------------------------------------------------------------------
    | Destination
    |--------------------------------------------------------------------------
    |
    | The filesystem disk backups are written to and the folder inside it. Any
    | disk works; an S3-compatible disk (AWS S3, Cloudflare R2, MinIO) keeps the
    | backups off the server they protect.
    |
    */

    'disk' => env('DB_BACKUP_DISK', 'local'),

    'path' => env('DB_BACKUP_PATH', 'db-backups'),

    /*
    |--------------------------------------------------------------------------
    | Retention
    |--------------------------------------------------------------------------
    |
    | db-backup:prune deletes successful backups beyond the newest "keep_last"
    | and those older than "keep_days". Null switches a rule off. The newest
    | successful backup is never pruned.
    |
    */

    'retention' => [
        'keep_last' => env('DB_BACKUP_KEEP_LAST', 14),
        'keep_days' => env('DB_BACKUP_KEEP_DAYS'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Schedule
    |--------------------------------------------------------------------------
    |
    | When enabled, the package schedules db-backup:run daily at "time",
    | db-backup:prune an hour later, and db-backup:monitor at "monitor_time".
    | Times are 24-hour HH:MM in "timezone" (null uses app.timezone). Laravel's
    | scheduler (schedule:run every minute) must be running.
    |
    */

    'schedule' => [
        'enabled' => env('DB_BACKUP_SCHEDULE', false),
        'time' => env('DB_BACKUP_TIME', '02:00'),
        'monitor_time' => env('DB_BACKUP_MONITOR_TIME', '08:00'),
        'timezone' => env('DB_BACKUP_TIMEZONE'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Monitoring
    |--------------------------------------------------------------------------
    |
    | db-backup:monitor fires BackupIsStale when the newest successful backup is
    | older than this many hours.
    |
    */

    'max_age_hours' => env('DB_BACKUP_MAX_AGE_HOURS', 26),

    /*
    |--------------------------------------------------------------------------
    | Dumping
    |--------------------------------------------------------------------------
    |
    | Seconds a dump or restore may run, the folder files are written to
    | before upload and after download, the dumper and restorer per database
    | driver, and the binaries and extra options they use. Point a binary at
    | an absolute path when it is not on PATH.
    |
    */

    'timeout' => env('DB_BACKUP_TIMEOUT', 3600),

    'temporary_directory' => storage_path('app/db-backup-temp'),

    'dumpers' => [
        'pgsql' => PostgresDumper::class,
        'mysql' => MySqlDumper::class,
        'mariadb' => MySqlDumper::class,
        'sqlite' => SqliteDumper::class,
    ],

    'restorers' => [
        'pgsql' => PostgresRestorer::class,
        'mysql' => MySqlRestorer::class,
        'mariadb' => MySqlRestorer::class,
        'sqlite' => SqliteRestorer::class,
    ],

    'binaries' => [
        'pg_dump' => env('DB_BACKUP_PG_DUMP', 'pg_dump'),
        'mysqldump' => env('DB_BACKUP_MYSQLDUMP', 'mysqldump'),
        'pg_restore' => env('DB_BACKUP_PG_RESTORE', 'pg_restore'),
        'mysql' => env('DB_BACKUP_MYSQL', 'mysql'),
    ],

    'options' => [
        'pgsql' => ['--no-owner', '--no-acl'],
        'mysql' => ['--single-transaction', '--quick', '--routines', '--triggers'],
    ],

    'restore_options' => [
        'pgsql' => ['--no-owner', '--no-acl'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue
    |--------------------------------------------------------------------------
    |
    | Where backups started with DbBackup::queue() run. Null uses the defaults.
    |
    */

    'queue' => [
        'connection' => env('DB_BACKUP_QUEUE_CONNECTION'),
        'name' => env('DB_BACKUP_QUEUE'),
    ],

];
