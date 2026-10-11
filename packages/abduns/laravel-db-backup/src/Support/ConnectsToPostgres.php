<?php

namespace Abduns\DbBackup\Support;

use Illuminate\Support\Arr;

/**
 * Connection flags and environment shared by pg_dump and pg_restore. The
 * password travels in PGPASSWORD, never on the command line.
 */
trait ConnectsToPostgres
{
    /**
     * @param  array<string, mixed>  $config
     * @return list<string>
     */
    protected function postgresArguments(array $config): array
    {
        return [
            '--host='.Arr::first(Arr::wrap($config['host'] ?? '127.0.0.1')),
            '--port='.($config['port'] ?? 5432),
            '--username='.($config['username'] ?? ''),
            '--dbname='.($config['database'] ?? ''),
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, string>
     */
    protected function postgresEnvironment(array $config): array
    {
        return array_filter([
            'PGPASSWORD' => (string) ($config['password'] ?? ''),
            'PGSSLMODE' => (string) ($config['sslmode'] ?? ''),
        ], fn (string $value): bool => $value !== '');
    }
}
