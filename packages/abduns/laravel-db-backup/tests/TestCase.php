<?php

namespace Abduns\DbBackup\Tests;

use Abduns\DbBackup\DbBackupServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

/**
 * Every test boots a fresh in-memory database, migrated without a wrapping
 * transaction: SQLite refuses VACUUM INTO inside one.
 */
abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [DbBackupServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('db-backup.disk', 'backups');
        $app['config']->set('db-backup.path', 'db-backups');
        $app['config']->set('db-backup.temporary_directory', sys_get_temp_dir().'/db-backup-tests');
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
