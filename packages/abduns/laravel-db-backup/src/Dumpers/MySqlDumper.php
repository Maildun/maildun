<?php

namespace Abduns\DbBackup\Dumpers;

use Abduns\DbBackup\Exceptions\DumpFailed;
use Abduns\DbBackup\Support\ConnectsToMySql;
use Illuminate\Support\Facades\Process;

/**
 * mysqldump (or mariadb-dump) to plain SQL, gzipped afterwards.
 */
class MySqlDumper implements Dumper
{
    use CompressesDumps, ConnectsToMySql;

    public function dump(string $connection, array $config, string $path): void
    {
        $binary = (string) config('db-backup.binaries.mysqldump', 'mysqldump');
        $sqlPath = $path.'.sql';
        $optionFile = $this->writeMySqlOptionFile((string) ($config['password'] ?? ''));

        $command = [
            $binary,
            '--defaults-extra-file='.$optionFile,
            ...array_values((array) config('db-backup.options.mysql', [])),
            ...$this->mySqlArguments($config),
            '--result-file='.$sqlPath,
            (string) ($config['database'] ?? ''),
        ];

        try {
            $result = Process::timeout((int) config('db-backup.timeout', 3600))->run($command);

            if ($result->failed()) {
                throw DumpFailed::fromProcess($binary, $result);
            }

            $this->gzip($sqlPath, $path);
        } finally {
            @unlink($optionFile);
            @unlink($sqlPath);
        }
    }

    public function extension(): string
    {
        return 'sql.gz';
    }
}
