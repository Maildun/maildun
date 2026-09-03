<?php

use Illuminate\Support\Env;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;

/**
 * @param  array<string, string>  $environment
 * @return array<string, mixed>
 */
function loadFilesystemConfigurationWithEnvironment(array $environment): array
{
    $repository = Env::getRepository();
    $originalEnvironment = [];

    foreach ($environment as $key => $value) {
        $originalEnvironment[$key] = [
            'env_exists' => array_key_exists($key, $_ENV),
            'env_value' => $_ENV[$key] ?? null,
            'server_exists' => array_key_exists($key, $_SERVER),
            'server_value' => $_SERVER[$key] ?? null,
            'process_value' => getenv($key),
            'repository_value' => Env::get($key),
        ];

        $repository->clear($key);
        putenv("{$key}={$value}");
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }

    try {
        return require config_path('filesystems.php');
    } finally {
        foreach ($originalEnvironment as $key => $original) {
            $repository->clear($key);
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);

            if ($original['process_value'] !== false) {
                putenv("{$key}={$original['process_value']}");
            }

            if ($original['env_exists']) {
                $_ENV[$key] = $original['env_value'];
            }

            if ($original['server_exists']) {
                $_SERVER[$key] = $original['server_value'];
            }

            if (! $original['env_exists'] && ! $original['server_exists'] && $original['repository_value'] !== null) {
                $repository->set($key, (string) $original['repository_value']);
            }
        }
    }
}

test('local storage is the zero configuration default', function () {
    $filesystems = loadFilesystemConfigurationWithEnvironment([
        'FILESYSTEM_DISK' => 'local',
    ]);

    expect($filesystems)
        ->default->toBe('local')
        ->and($filesystems['disks']['local'])
        ->driver->toBe('local')
        ->and($filesystems['disks']['public'])
        ->driver->toBe('local')
        ->url->toBe('/storage')
        ->visibility->toBe('public');
});

test('R2 mode reads only dedicated Cloudflare settings', function () {
    $environment = [
        'FILESYSTEM_DISK' => 's3',
        'OBJECT_STORAGE_PROVIDER' => 'r2',
        'AWS_BUCKET' => 'ignored-aws-bucket',
        'R2_ACCESS_KEY_ID' => 'r2-access-key',
        'R2_SECRET_ACCESS_KEY' => 'r2-secret-key',
        'R2_DEFAULT_REGION' => 'auto',
        'R2_BUCKET' => 'maildun-private',
        'R2_PUBLIC_BUCKET' => 'maildun-public',
        'R2_PUBLIC_URL' => 'https://assets.example.com',
        'R2_ENDPOINT' => 'https://account.r2.cloudflarestorage.com',
        'R2_USE_PATH_STYLE_ENDPOINT' => 'false',
    ];
    $filesystems = loadFilesystemConfigurationWithEnvironment($environment);

    expect(class_exists(AwsS3V3Adapter::class))->toBeTrue()
        ->and($filesystems)
        ->default->toBe('s3')
        ->object_storage_provider->toBe('r2')
        ->and($filesystems['disks']['s3'])
        ->driver->toBe('s3')
        ->key->toBe('r2-access-key')
        ->bucket->toBe('maildun-private')
        ->endpoint->toBe('https://account.r2.cloudflarestorage.com')
        ->and($filesystems['disks']['public'])
        ->driver->toBe('s3')
        ->bucket->toBe('maildun-public')
        ->url->toBe('https://assets.example.com')
        ->endpoint->toBe('https://account.r2.cloudflarestorage.com')
        ->not->toHaveKey('visibility');

    config()->set('filesystems.disks.public', $filesystems['disks']['public']);
    Storage::forgetDisk('public');

    expect(Storage::disk('public')->url('media/team/hero.webp'))
        ->toBe('https://assets.example.com/media/team/hero.webp');
});

test('AWS S3 mode ignores Cloudflare settings', function () {
    $environment = [
        'FILESYSTEM_DISK' => 's3',
        'OBJECT_STORAGE_PROVIDER' => 'aws',
        'AWS_ACCESS_KEY_ID' => 'aws-access-key',
        'AWS_SECRET_ACCESS_KEY' => 'aws-secret-key',
        'AWS_DEFAULT_REGION' => 'us-east-1',
        'AWS_BUCKET' => 'aws-private',
        'AWS_PUBLIC_BUCKET' => 'aws-public',
        'R2_BUCKET' => 'ignored-r2-bucket',
    ];
    $filesystems = loadFilesystemConfigurationWithEnvironment($environment);

    expect($filesystems)
        ->object_storage_provider->toBe('aws')
        ->and($filesystems['disks']['s3'])
        ->key->toBe('aws-access-key')
        ->bucket->toBe('aws-private')
        ->and($filesystems['disks']['s3_public'])
        ->bucket->toBe('aws-public');
});
