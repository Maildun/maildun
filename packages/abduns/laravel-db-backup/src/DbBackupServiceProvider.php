<?php

namespace Abduns\DbBackup;

use Abduns\DbBackup\Commands\MonitorBackupsCommand;
use Abduns\DbBackup\Commands\PruneBackupsCommand;
use Abduns\DbBackup\Commands\RestoreBackupCommand;
use Abduns\DbBackup\Commands\RunBackupCommand;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\ServiceProvider;

class DbBackupServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/db-backup.php', 'db-backup');

        $this->app->singleton(BackupManager::class);
    }

    public function boot(): void
    {
        $this->callAfterResolving(Schedule::class, fn (Schedule $schedule) => $this->schedule($schedule));

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/db-backup.php' => config_path('db-backup.php'),
        ], 'db-backup-config');

        $this->publishesMigrations([
            __DIR__.'/../database/migrations/2026_10_11_000000_create_database_backups_table.php' => database_path('migrations/2026_10_11_000000_create_database_backups_table.php'),
        ], 'db-backup-migrations');

        $this->commands([
            RunBackupCommand::class,
            PruneBackupsCommand::class,
            MonitorBackupsCommand::class,
            RestoreBackupCommand::class,
        ]);
    }

    /**
     * Register the daily backup, the prune an hour after it, and the health
     * check, when db-backup.schedule.enabled is on.
     */
    private function schedule(Schedule $schedule): void
    {
        if (! (bool) config('db-backup.schedule.enabled')) {
            return;
        }

        $backups = $this->app->make(BackupManager::class);
        $timezone = $backups->scheduleTimezone();
        $time = $backups->scheduleTime();

        $schedule->command('db-backup:run')
            ->dailyAt($time)
            ->timezone($timezone)
            ->withoutOverlapping()
            ->description('Back up the database');

        $schedule->command('db-backup:prune')
            ->dailyAt(Carbon::createFromFormat('H:i', $time)->addHour()->format('H:i'))
            ->timezone($timezone)
            ->withoutOverlapping()
            ->description('Delete database backups the retention rules no longer keep');

        $schedule->command('db-backup:monitor')
            ->dailyAt((string) config('db-backup.schedule.monitor_time', '08:00'))
            ->timezone($timezone)
            ->description('Check that a recent database backup succeeded');
    }
}
