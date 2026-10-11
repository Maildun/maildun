<?php

namespace Abduns\DbBackup\Restorers;

use Abduns\DbBackup\Exceptions\RestoreFailed;
use Abduns\DbBackup\Support\ConnectsToMySql;
use Illuminate\Support\Facades\Process;

/**
 * Streams the gzipped SQL dump into the mysql client without unpacking it to disk.
 */
class MySqlRestorer implements Restorer
{
    use ConnectsToMySql;

    public function restore(string $connection, array $config, string $path): void
    {
        $binary = (string) config('db-backup.binaries.mysql', 'mysql');
        $input = @fopen('compress.zlib://'.$path, 'rb');

        if ($input === false) {
            throw new RestoreFailed(sprintf('Could not read the dump at %s.', $path));
        }

        $optionFile = $this->writeMySqlOptionFile((string) ($config['password'] ?? ''));

        try {
            $result = Process::timeout((int) config('db-backup.timeout', 3600))
                ->input($input)
                ->run([
                    $binary,
                    '--defaults-extra-file='.$optionFile,
                    ...$this->mySqlArguments($config),
                    (string) ($config['database'] ?? ''),
                ]);

            if ($result->failed()) {
                throw RestoreFailed::fromProcess($binary, $result);
            }
        } finally {
            if (is_resource($input)) {
                fclose($input);
            }

            @unlink($optionFile);
        }
    }

    public function needsEmptyDatabase(): bool
    {
        return true;
    }
}
