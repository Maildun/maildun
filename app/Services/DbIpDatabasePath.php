<?php

namespace App\Services;

final class DbIpDatabasePath
{
    /**
     * Resolve a configured MMDB path so auto-updates and lookups share one file.
     *
     * Relative values such as storage/app/private/geoip/dbip-city-lite.mmdb are
     * resolved from the project root. Absolute paths are left unchanged.
     */
    public static function resolve(mixed $path): ?string
    {
        if (! is_string($path)) {
            return null;
        }

        $path = trim($path);

        if ($path === '') {
            return null;
        }

        if (self::isAbsolute($path)) {
            return $path;
        }

        return base_path($path);
    }

    private static function isAbsolute(string $path): bool
    {
        if (str_starts_with($path, '/') || str_starts_with($path, '\\')) {
            return true;
        }

        return strlen($path) > 2
            && ctype_alpha($path[0])
            && $path[1] === ':'
            && in_array($path[2], ['/', '\\'], true);
    }
}
