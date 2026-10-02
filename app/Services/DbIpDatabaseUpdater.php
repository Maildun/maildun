<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class DbIpDatabaseUpdater
{
    public function __construct(private DbIpDatabaseInspector $inspector) {}

    /**
     * @return array{city: 'current'|'updated', asn: 'current'|'updated'}
     */
    public function update(bool $force = false): array
    {
        $releaseMonth = now('UTC')->format('Y-m');
        $databases = $this->databases();

        return [
            'city' => $this->updateDatabase(
                database: 'city',
                databasePath: $databases['city']['path'],
                downloadName: $databases['city']['download_name'],
                releaseMonth: $releaseMonth,
                force: $force,
            ),
            'asn' => $this->updateDatabase(
                database: 'asn',
                databasePath: $databases['asn']['path'],
                downloadName: $databases['asn']['download_name'],
                releaseMonth: $releaseMonth,
                force: $force,
            ),
        ];
    }

    /**
     * @return array{city: array{path: string, download_name: string}, asn: array{path: string, download_name: string}}
     */
    private function databases(): array
    {
        return [
            'city' => [
                'path' => $this->configuredPath('tracking.geolocation.city_database'),
                'download_name' => 'dbip-city-lite',
            ],
            'asn' => [
                'path' => $this->configuredPath('tracking.geolocation.asn_database'),
                'download_name' => 'dbip-asn-lite',
            ],
        ];
    }

    /** @return 'current'|'updated' */
    private function updateDatabase(
        string $database,
        string $databasePath,
        string $downloadName,
        string $releaseMonth,
        bool $force,
    ): string {
        if (! $force && $this->inspector->releaseMonth($databasePath) === $releaseMonth) {
            return 'current';
        }

        $directory = dirname($databasePath);
        File::ensureDirectoryExists($directory);
        $archivePath = $this->temporaryPath($directory, 'archive');
        $candidatePath = $this->temporaryPath($directory, 'database');

        try {
            $response = Http::sink($archivePath)
                ->connectTimeout(10)
                ->timeout(300)
                ->retry([1000, 3000], throw: false)
                ->get($this->downloadUrl($downloadName, $releaseMonth));

            if (! $response->successful()) {
                throw new RuntimeException("The {$database} database download returned HTTP {$response->status()}.");
            }

            $this->extractGzip($archivePath, $candidatePath);

            if (! $this->inspector->isValid($candidatePath, $database)) {
                throw new RuntimeException("The downloaded {$database} database is not a valid DB-IP Lite MMDB file.");
            }

            if (! chmod($candidatePath, 0644)) {
                throw new RuntimeException("The downloaded {$database} database permissions could not be set.");
            }

            if (! rename($candidatePath, $databasePath)) {
                throw new RuntimeException("The downloaded {$database} database could not be activated.");
            }
        } finally {
            File::delete([$archivePath, $candidatePath]);
        }

        return 'updated';
    }

    private function configuredPath(string $key): string
    {
        $path = DbIpDatabasePath::resolve(config($key));

        if ($path === null) {
            throw new RuntimeException("The {$key} path is not configured.");
        }

        return $path;
    }

    private function downloadUrl(string $downloadName, string $releaseMonth): string
    {
        $baseUrl = config('tracking.geolocation.auto_update.download_base_url');

        if (
            ! is_string($baseUrl)
            || filter_var($baseUrl, FILTER_VALIDATE_URL) === false
            || parse_url($baseUrl, PHP_URL_SCHEME) !== 'https'
        ) {
            throw new RuntimeException('The DB-IP Lite download URL is invalid.');
        }

        return rtrim($baseUrl, '/')."/{$downloadName}-{$releaseMonth}.mmdb.gz";
    }

    private function temporaryPath(string $directory, string $purpose): string
    {
        $path = tempnam($directory, ".dbip-{$purpose}-");

        if ($path === false) {
            throw new RuntimeException("A temporary {$purpose} file could not be created.");
        }

        return $path;
    }

    private function extractGzip(string $archivePath, string $candidatePath): void
    {
        $archive = gzopen($archivePath, 'rb');
        $candidate = fopen($candidatePath, 'wb');

        if ($archive === false || $candidate === false) {
            if (is_resource($archive)) {
                gzclose($archive);
            }

            if (is_resource($candidate)) {
                fclose($candidate);
            }

            throw new RuntimeException('The DB-IP Lite archive could not be opened.');
        }

        try {
            while (! gzeof($archive)) {
                $chunk = gzread($archive, 1024 * 1024);

                if ($chunk === false || fwrite($candidate, $chunk) !== strlen($chunk)) {
                    throw new RuntimeException('The DB-IP Lite archive could not be extracted.');
                }
            }

            if (! fflush($candidate)) {
                throw new RuntimeException('The extracted DB-IP Lite database could not be flushed.');
            }
        } finally {
            gzclose($archive);
            fclose($candidate);
        }

        if (filesize($candidatePath) === 0) {
            throw new RuntimeException('The extracted DB-IP Lite database is empty.');
        }
    }
}
