<?php

namespace Abduns\DbBackup\Support;

use Illuminate\Support\Arr;
use RuntimeException;

/**
 * Connection flags shared by mysqldump and mysql. The password goes through a
 * private option file so it never shows up in the process list; the file must
 * be the first argument and deleted afterwards.
 */
trait ConnectsToMySql
{
    /**
     * @param  array<string, mixed>  $config
     * @return list<string>
     */
    protected function mySqlArguments(array $config): array
    {
        $location = filled($config['unix_socket'] ?? null)
            ? ['--socket='.$config['unix_socket']]
            : [
                '--host='.Arr::first(Arr::wrap($config['host'] ?? '127.0.0.1')),
                '--port='.($config['port'] ?? 3306),
            ];

        return [
            ...$location,
            '--user='.($config['username'] ?? ''),
            '--default-character-set='.($config['charset'] ?? 'utf8mb4'),
        ];
    }

    protected function writeMySqlOptionFile(string $password): string
    {
        $file = tempnam(sys_get_temp_dir(), 'db-backup-');

        if ($file === false) {
            throw new RuntimeException('Could not create the MySQL option file.');
        }

        chmod($file, 0600);
        file_put_contents($file, "[client]\npassword=\"".addcslashes($password, '\\"')."\"\n");

        return $file;
    }
}
