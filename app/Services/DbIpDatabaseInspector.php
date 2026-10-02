<?php

namespace App\Services;

use Illuminate\Support\Arr;
use MaxMind\Db\Reader;
use Throwable;

class DbIpDatabaseInspector
{
    public function releaseMonth(?string $databasePath): ?string
    {
        $metadata = $this->metadata($databasePath);
        $buildEpoch = $metadata['build_epoch'] ?? null;

        if (! is_int($buildEpoch) || $buildEpoch <= 0) {
            return null;
        }

        return gmdate('Y-m', $buildEpoch);
    }

    public function isValid(string $databasePath, string $database): bool
    {
        if (! in_array($database, ['city', 'asn'], true)) {
            return false;
        }

        try {
            $reader = new Reader($databasePath);

            try {
                $metadata = $reader->metadata();
                $record = $reader->get('8.8.8.8');
            } finally {
                $reader->close();
            }
        } catch (Throwable) {
            return false;
        }

        if (! is_array($record)) {
            return false;
        }

        $databaseType = mb_strtolower($metadata->databaseType);

        return match ($database) {
            'city' => str_contains($databaseType, 'city')
                && is_string(Arr::get($record, 'country.iso_code')),
            'asn' => str_contains($databaseType, 'asn')
                && is_numeric(Arr::get($record, 'autonomous_system_number')),
        };
    }

    /**
     * @return array{database_type: string, build_epoch: int}|null
     */
    private function metadata(?string $databasePath): ?array
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
                $metadata = $reader->metadata();
            } finally {
                $reader->close();
            }
        } catch (Throwable) {
            return null;
        }

        return [
            'database_type' => $metadata->databaseType,
            'build_epoch' => $metadata->buildEpoch,
        ];
    }
}
