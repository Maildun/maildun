<?php

namespace Abduns\DbBackup\Dumpers;

use Abduns\DbBackup\Exceptions\DumpFailed;
use Abduns\DbBackup\Support\ConnectsToPostgres;
use Illuminate\Support\Facades\Process;

/**
 * pg_dump in its custom format: compressed already, and pg_restore can bring
 * back the whole database or single tables from it.
 */
class PostgresDumper implements Dumper
{
    use ConnectsToPostgres;

    public function dump(string $connection, array $config, string $path): void
    {
        $binary = (string) config('db-backup.binaries.pg_dump', 'pg_dump');

        $command = [
            $binary,
            '--format=custom',
            ...array_values((array) config('db-backup.options.pgsql', [])),
            '--file='.$path,
            ...$this->postgresArguments($config),
        ];

        $result = Process::timeout((int) config('db-backup.timeout', 3600))
            ->env($this->postgresEnvironment($config))
            ->run($command);

        if ($result->failed()) {
            throw DumpFailed::fromProcess($binary, $result);
        }
    }

    public function extension(): string
    {
        return 'dump';
    }
}
