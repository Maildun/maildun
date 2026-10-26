<?php

namespace Abduns\DbBackup\Dumpers;

use Abduns\DbBackup\Exceptions\DumpFailed;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * VACUUM INTO writes a consistent copy of the live database without a binary,
 * which a plain file copy cannot promise while the app is writing. Needs
 * SQLite 3.27 or newer.
 */
class SqliteDumper implements Dumper
{
    use CompressesDumps;

    public function dump(string $connection, array $config, string $path): void
    {
        $copyPath = $path.'.sqlite';

        try {
            DB::connection($connection)->statement('VACUUM INTO ?', [$copyPath]);

            $this->gzip($copyPath, $path);
        } catch (DumpFailed $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new DumpFailed('VACUUM INTO failed: '.$exception->getMessage(), previous: $exception);
        } finally {
            @unlink($copyPath);
        }
    }

    public function extension(): string
    {
        return 'sqlite.gz';
    }
}
