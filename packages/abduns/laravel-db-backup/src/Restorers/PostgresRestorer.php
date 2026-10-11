<?php

namespace Abduns\DbBackup\Restorers;

use Abduns\DbBackup\Exceptions\RestoreFailed;
use Abduns\DbBackup\Support\ConnectsToPostgres;
use Illuminate\Support\Facades\Process;

/**
 * pg_restore in one transaction, stopping at the first error, so a dump that
 * does not load leaves the (emptied) database untouched rather than half full.
 */
class PostgresRestorer implements Restorer
{
    use ConnectsToPostgres;

    public function restore(string $connection, array $config, string $path): void
    {
        $binary = (string) config('db-backup.binaries.pg_restore', 'pg_restore');

        $command = [
            $binary,
            '--single-transaction',
            '--exit-on-error',
            ...array_values((array) config('db-backup.restore_options.pgsql', [])),
            ...$this->postgresArguments($config),
            $path,
        ];

        $result = Process::timeout((int) config('db-backup.timeout', 3600))
            ->env($this->postgresEnvironment($config))
            ->run($command);

        if ($result->failed()) {
            throw RestoreFailed::fromProcess($binary, $result);
        }
    }

    public function needsEmptyDatabase(): bool
    {
        return true;
    }
}
