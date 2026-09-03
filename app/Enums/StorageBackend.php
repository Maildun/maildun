<?php

namespace App\Enums;

use Illuminate\Support\Facades\Storage;

/**
 * The two storage backends the app can run on. Each one owns a private disk for
 * uploads that must not be web-readable and a public disk for served assets.
 */
enum StorageBackend: string
{
    case Local = 'local';
    case S3 = 's3';

    /**
     * The backend FILESYSTEM_DISK currently selects.
     *
     * Anything other than "s3" falls back to local, matching config/filesystems.php.
     */
    public static function current(): self
    {
        return self::tryFrom((string) Storage::getDefaultDriver()) ?? self::Local;
    }

    public function privateDisk(): string
    {
        return $this->value;
    }

    public function publicDisk(): string
    {
        return match ($this) {
            self::Local => 'local_public',
            self::S3 => 's3_public',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Local => 'local filesystem',
            self::S3 => match (config('filesystems.object_storage_provider')) {
                'r2' => 'Cloudflare R2',
                'aws' => 'AWS S3',
                default => 'S3-compatible storage',
            },
        };
    }

    public function other(): self
    {
        return match ($this) {
            self::Local => self::S3,
            self::S3 => self::Local,
        };
    }

    /**
     * Config values that must be filled before this backend works, mapped to
     * the .env variable that fills each one.
     *
     * Read through config rather than env() so the check stays correct once
     * config:cache has run, where env() returns null for everything.
     *
     * @return array<string, string>
     */
    public function requiredConfiguration(): array
    {
        if ($this === self::Local) {
            return [];
        }

        $isR2 = config('filesystems.object_storage_provider') === 'r2';
        $prefix = $isR2 ? 'R2' : 'AWS';

        $required = [
            'filesystems.disks.s3.key' => $prefix.'_ACCESS_KEY_ID',
            'filesystems.disks.s3.secret' => $prefix.'_SECRET_ACCESS_KEY',
            'filesystems.disks.s3.region' => $prefix.'_DEFAULT_REGION',
            'filesystems.disks.s3.bucket' => $prefix.'_BUCKET',
            'filesystems.disks.s3_public.bucket' => $prefix.'_PUBLIC_BUCKET',
        ];

        if ($isR2) {
            $required['filesystems.disks.s3.endpoint'] = 'R2_ENDPOINT';
            $required['filesystems.disks.s3_public.url'] = 'R2_PUBLIC_URL';

            return $required;
        }

        /*
         * A custom endpoint means the bucket host does not serve public reads
         * itself (Cloudflare R2, MinIO), so a CDN or custom domain is
         * mandatory. Plain AWS S3 can fall back to the bucket URL.
         */
        if (filled(config('filesystems.disks.s3_public.endpoint'))) {
            $required['filesystems.disks.s3_public.url'] = $prefix.'_PUBLIC_URL';
        }

        return $required;
    }
}
