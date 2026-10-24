<?php

namespace Abduns\DbBackup\Restorers;

use Abduns\DbBackup\Exceptions\RestoreFailed;

/**
 * Loads a dump made by the matching Dumper back into a database connection.
 */
interface Restorer
{
    /**
     * Load the dump at $path into the connection, throwing when it fails.
     *
     * @param  array<string, mixed>  $config  The resolved connection configuration.
     *
     * @throws RestoreFailed
     */
    public function restore(string $connection, array $config, string $path): void;

    /**
     * Whether every table, view and type must be dropped before restore()
     * runs. False when restore() replaces the whole database itself.
     */
    public function needsEmptyDatabase(): bool;
}
