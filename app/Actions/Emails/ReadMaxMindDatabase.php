<?php

namespace App\Actions\Emails;

use MaxMind\Db\Reader;
use Throwable;

class ReadMaxMindDatabase
{
    /**
     * @return array<string, mixed>|null
     */
    public function handle(?string $databasePath, string $ipAddress): ?array
    {
        if (
            $databasePath === null
            || $databasePath === ''
            || ! is_file($databasePath)
            || ! is_readable($databasePath)
        ) {
            return null;
        }

        try {
            $reader = new Reader($databasePath);

            try {
                $record = $reader->get($ipAddress);
            } finally {
                $reader->close();
            }
        } catch (Throwable) {
            return null;
        }

        return is_array($record) ? $record : null;
    }
}
