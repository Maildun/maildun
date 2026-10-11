<?php

namespace Abduns\DbBackup\Restorers;

use Abduns\DbBackup\Exceptions\RestoreFailed;
use Illuminate\Support\Facades\DB;

/**
 * Unpacks the copy next to the database file and swaps it in with a rename,
 * so the database is either the old file or the restored one, never half of each.
 */
class SqliteRestorer implements Restorer
{
    public function restore(string $connection, array $config, string $path): void
    {
        $database = (string) ($config['database'] ?? '');

        if ($database === '' || $database === ':memory:') {
            throw new RestoreFailed('An in-memory SQLite database cannot be restored.');
        }

        $unpacked = $database.'.restoring';
        $input = @fopen('compress.zlib://'.$path, 'rb');
        $output = @fopen($unpacked, 'wb');

        if ($input === false || $output === false) {
            throw new RestoreFailed(sprintf('Could not unpack the dump next to %s.', $database));
        }

        try {
            if (stream_copy_to_stream($input, $output) === false) {
                throw new RestoreFailed(sprintf('Could not unpack the dump next to %s.', $database));
            }
        } finally {
            fclose($input);
            fclose($output);
        }

        DB::purge($connection);

        foreach (['-wal', '-shm'] as $suffix) {
            @unlink($database.$suffix);
        }

        if (! @rename($unpacked, $database)) {
            @unlink($unpacked);

            throw new RestoreFailed(sprintf('Could not replace %s with the restored copy.', $database));
        }
    }

    public function needsEmptyDatabase(): bool
    {
        return false;
    }
}
