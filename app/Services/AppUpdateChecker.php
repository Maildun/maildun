<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

final class AppUpdateChecker
{
    private const string CacheKey = 'app-update:status';

    /**
     * @return array{status: 'current'|'disabled'|'unknown'|'unsupported'|'update_available', current_version: string, latest_version: ?string, minimum_version: ?string, release_url: ?string, notes_url: ?string, checked_at: ?string}
     */
    public function status(): array
    {
        $currentVersion = $this->currentVersion();

        if (! $this->enabled()) {
            return $this->disabledStatus($currentVersion);
        }

        $cached = Cache::get(self::CacheKey);

        if (! is_array($cached)) {
            return $this->unknownStatus($currentVersion);
        }

        $latestVersion = $cached['latest_version'] ?? null;

        if (! is_string($latestVersion) || ! $this->isVersion($latestVersion)) {
            return $this->unknownStatus($currentVersion);
        }

        $minimumVersion = $cached['minimum_version'] ?? null;
        $minimumVersion = is_string($minimumVersion) && $this->isVersion($minimumVersion)
            ? $minimumVersion
            : null;

        $status = version_compare($currentVersion, $minimumVersion ?? $currentVersion, '<')
            ? 'unsupported'
            : (version_compare($latestVersion, $currentVersion, '>') ? 'update_available' : 'current');

        return [
            'status' => $status,
            'current_version' => $currentVersion,
            'latest_version' => $latestVersion,
            'minimum_version' => $minimumVersion,
            'release_url' => $this->cachedUrl($cached, 'release_url'),
            'notes_url' => $this->cachedUrl($cached, 'notes_url'),
            'checked_at' => is_string($cached['checked_at'] ?? null) ? $cached['checked_at'] : null,
        ];
    }

    /**
     * Fetch and cache the official release manifest.
     */
    public function refresh(): bool
    {
        if (! $this->enabled()) {
            return false;
        }

        $manifestUrl = $this->manifestUrl();

        if ($manifestUrl === null) {
            return false;
        }

        try {
            $response = Http::acceptJson()
                ->connectTimeout(3)
                ->timeout(5)
                ->get($manifestUrl);

            if (! $response->successful()) {
                throw new RuntimeException("The update manifest returned HTTP {$response->status()}.");
            }

            $manifest = $this->manifest($response->json());

            Cache::forever(self::CacheKey, [
                ...$manifest,
                'checked_at' => now()->toIso8601String(),
            ]);

            return true;
        } catch (Throwable $exception) {
            Log::warning('Unable to check for a Maildun update.', [
                'manifest_url' => $manifestUrl,
                'exception' => $exception,
            ]);

            return false;
        }
    }

    private function currentVersion(): string
    {
        return $this->normalizeVersion(config('version.current')) ?? '0.0.0';
    }

    public function enabled(): bool
    {
        return (bool) config('version.update_check.enabled') && $this->manifestUrl() !== null;
    }

    private function manifestUrl(): ?string
    {
        return $this->validUrl(config('version.update_check.manifest_url'));
    }

    /**
     * @return array{latest_version: string, minimum_version: ?string, release_url: string, notes_url: string}
     */
    private function manifest(mixed $manifest): array
    {
        if (! is_array($manifest)) {
            throw new RuntimeException('The update manifest must be a JSON object.');
        }

        $latestVersion = $this->normalizeVersion($manifest['latest_version'] ?? null);
        $releaseUrl = $this->validUrl($manifest['release_url'] ?? null);
        $notesUrl = $this->validUrl($manifest['notes_url'] ?? null);

        if ($latestVersion === null || $releaseUrl === null || $notesUrl === null) {
            throw new RuntimeException('The update manifest is missing required release information.');
        }

        $minimumVersion = $manifest['minimum_version'] ?? null;
        $minimumVersion = $minimumVersion === null
            ? null
            : $this->normalizeVersion($minimumVersion);

        if (($manifest['minimum_version'] ?? null) !== null && $minimumVersion === null) {
            throw new RuntimeException('The update manifest has an invalid minimum version.');
        }

        if ($minimumVersion !== null && version_compare($minimumVersion, $latestVersion, '>')) {
            throw new RuntimeException('The update manifest minimum version cannot be newer than the latest version.');
        }

        return [
            'latest_version' => $latestVersion,
            'minimum_version' => $minimumVersion,
            'release_url' => $releaseUrl,
            'notes_url' => $notesUrl,
        ];
    }

    /**
     * @return array{status: 'disabled'|'unknown', current_version: string, latest_version: null, minimum_version: null, release_url: null, notes_url: null, checked_at: null}
     */
    private function unknownStatus(string $currentVersion): array
    {
        return [
            'status' => 'unknown',
            'current_version' => $currentVersion,
            'latest_version' => null,
            'minimum_version' => null,
            'release_url' => null,
            'notes_url' => null,
            'checked_at' => null,
        ];
    }

    /**
     * @return array{status: 'disabled', current_version: string, latest_version: null, minimum_version: null, release_url: null, notes_url: null, checked_at: null}
     */
    private function disabledStatus(string $currentVersion): array
    {
        return [
            'status' => 'disabled',
            'current_version' => $currentVersion,
            'latest_version' => null,
            'minimum_version' => null,
            'release_url' => null,
            'notes_url' => null,
            'checked_at' => null,
        ];
    }

    /**
     * @param  array<mixed>  $cached
     */
    private function cachedUrl(array $cached, string $key): ?string
    {
        return $this->validUrl($cached[$key] ?? null);
    }

    private function normalizeVersion(mixed $version): ?string
    {
        if (! is_string($version)) {
            return null;
        }

        $version = ltrim(trim($version), 'vV');

        return $this->isVersion($version) ? $version : null;
    }

    private function isVersion(string $version): bool
    {
        return preg_match('/^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?(?:\+[0-9A-Za-z.-]+)?$/', $version) === 1;
    }

    private function validUrl(mixed $url): ?string
    {
        if (! is_string($url) || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        return parse_url($url, PHP_URL_SCHEME) === 'https' ? $url : null;
    }
}
