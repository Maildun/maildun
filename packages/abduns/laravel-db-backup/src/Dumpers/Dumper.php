<?php

namespace Abduns\DbBackup\Dumpers;

use Abduns\DbBackup\Exceptions\DumpFailed;

/**
 * Writes a restorable copy of one database connection to a local file.
 */
interface Dumper
{
    /**
     * Dump the connection to $path, throwing when the dump fails.
     *
     * @param  array<string, mixed>  $config  The resolved connection configuration.
     *
     * @throws DumpFailed
     */
    public function dump(string $connection, array $config, string $path): void;

    /**
     * The file extension of the dump, without a leading dot.
     */
    public function extension(): string;
}
