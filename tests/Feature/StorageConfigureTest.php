<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * Keys the command pushes into the live process so config reloads see them.
 * They outlive a single test, so each test snapshots and restores them.
 */
const STORAGE_ENV_KEYS = [
    'FILESYSTEM_DISK',
    'OBJECT_STORAGE_PROVIDER',
    'AWS_ACCESS_KEY_ID',
    'AWS_SECRET_ACCESS_KEY',
    'AWS_SESSION_TOKEN',
    'AWS_DEFAULT_REGION',
    'AWS_ENDPOINT',
    'AWS_BUCKET',
    'AWS_URL',
    'AWS_PUBLIC_BUCKET',
    'AWS_PUBLIC_URL',
    'AWS_USE_PATH_STYLE_ENDPOINT',
    'R2_ACCESS_KEY_ID',
    'R2_SECRET_ACCESS_KEY',
    'R2_DEFAULT_REGION',
    'R2_ENDPOINT',
    'R2_BUCKET',
    'R2_URL',
    'R2_PUBLIC_BUCKET',
    'R2_PUBLIC_URL',
    'R2_USE_PATH_STYLE_ENDPOINT',
];

/**
 * The command edits a real .env, so every test points the application at a
 * throwaway file first. Touching the project's own .env would be destructive.
 */
beforeEach(function () {
    $this->originalEnv = [];

    foreach (STORAGE_ENV_KEYS as $key) {
        $originalValue = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        $this->originalEnv[$key] = is_string($originalValue) ? $originalValue : null;

        unset($_ENV[$key], $_SERVER[$key]);
        putenv($key);
    }

    $this->originalFilesystems = config('filesystems');

    $this->envDirectory = sys_get_temp_dir().'/maildun-storage-'.bin2hex(random_bytes(6));
    File::ensureDirectoryExists($this->envDirectory);

    $this->app->useEnvironmentPath($this->envDirectory);
    $this->app->loadEnvironmentFrom('.env');

    /*
     * The command re-requires config/filesystems.php after writing .env, which
     * would reset any Storage::fake. Redirecting the storage path instead keeps
     * the local disks pointed at a temp directory across that reload.
     */
    $this->storageDirectory = $this->envDirectory.'/storage';
    File::ensureDirectoryExists($this->storageDirectory.'/app/public');
    File::ensureDirectoryExists($this->storageDirectory.'/app/private');
    $this->app->useStoragePath($this->storageDirectory);

    File::put($this->app->environmentFilePath(), <<<'ENV'
    APP_NAME=Maildun
    FILESYSTEM_DISK=local
    AWS_ACCESS_KEY_ID=
    QUEUE_CONNECTION=database
    ENV);
});

afterEach(function () {
    foreach ($this->originalEnv as $key => $value) {
        if ($value === null) {
            unset($_ENV[$key], $_SERVER[$key]);
            putenv($key);

            continue;
        }

        $_ENV[$key] = $_SERVER[$key] = $value;
        putenv($key.'='.$value);
    }

    config()->set('filesystems', $this->originalFilesystems);

    foreach (array_keys($this->originalFilesystems['disks']) as $disk) {
        Storage::forgetDisk($disk);
    }

    File::deleteDirectory($this->envDirectory);
});

function envContents(): string
{
    return (string) File::get(app()->environmentFilePath());
}

test('choosing local storage writes the disk and links the public directory', function () {
    $this->artisan('storage:configure', ['--backend' => 'local'])
        ->expectsOutputToContain('local filesystem')
        ->assertSuccessful()
        ->run();

    expect(envContents())
        ->toContain('FILESYSTEM_DISK=local')
        ->and(substr_count(envContents(), 'FILESYSTEM_DISK='))->toBe(1);
});

test('choosing Cloudflare R2 writes only R2 storage settings', function () {
    $this->artisan('storage:configure', ['--backend' => 's3', '--skip-verify' => true])
        ->expectsChoice('Which S3-compatible service?', 'r2', [
            'aws' => 'AWS S3',
            'r2' => 'Cloudflare R2',
            'other' => 'Something else (MinIO, DigitalOcean Spaces, …)',
        ])
        ->expectsQuestion('S3 API endpoint (https://<ACCOUNT_ID>.r2.cloudflarestorage.com)', 'https://acct.r2.cloudflarestorage.com')
        ->expectsQuestion('Access key ID', 'access-key')
        ->expectsQuestion('Secret access key', 'secret-key')
        ->expectsQuestion('Region', 'auto')
        ->expectsQuestion('Bucket for private files (attachments, pending uploads)', 'maildun-private')
        ->expectsQuestion('Bucket for public assets (media, logos, avatars)', 'maildun-public')
        ->expectsQuestion('Public base URL for assets, without a trailing slash (custom domain or CDN)', 'https://assets.example.com')
        ->assertSuccessful()
        ->run();

    $contents = envContents();

    expect($contents)
        ->toContain('FILESYSTEM_DISK=s3')
        ->toContain('OBJECT_STORAGE_PROVIDER=r2')
        ->toContain('R2_ACCESS_KEY_ID=access-key')
        ->toContain('R2_SECRET_ACCESS_KEY=secret-key')
        ->toContain('R2_DEFAULT_REGION=auto')
        ->toContain('R2_ENDPOINT=https://acct.r2.cloudflarestorage.com')
        ->toContain('R2_BUCKET=maildun-private')
        ->toContain('R2_PUBLIC_BUCKET=maildun-public')
        ->toContain('R2_PUBLIC_URL=https://assets.example.com')
        ->toContain('R2_USE_PATH_STYLE_ENDPOINT=false');

    expect(substr_count($contents, 'R2_ACCESS_KEY_ID='))->toBe(1)
        ->and(substr_count($contents, 'FILESYSTEM_DISK='))->toBe(1);

    expect($contents)->toContain('APP_NAME=Maildun')
        ->and($contents)->toContain('QUEUE_CONNECTION=database');
});

test('custom storage enables path-style addressing and fails when it cannot be written to', function () {
    $this->artisan('storage:configure', ['--backend' => 's3'])
        ->expectsChoice('Which S3-compatible service?', 'other', [
            'aws' => 'AWS S3',
            'r2' => 'Cloudflare R2',
            'other' => 'Something else (MinIO, DigitalOcean Spaces, …)',
        ])
        ->expectsQuestion('S3 API endpoint', 'https://127.0.0.1:9')
        ->expectsQuestion('Access key ID', 'nope')
        ->expectsQuestion('Secret access key', 'nope')
        ->expectsQuestion('Region', 'auto')
        ->expectsQuestion('Bucket for private files (attachments, pending uploads)', 'missing')
        ->expectsQuestion('Bucket for public assets (media, logos, avatars)', 'missing')
        ->expectsQuestion('Public base URL for assets, without a trailing slash (custom domain or CDN)', 'https://assets.example.com')
        ->expectsOutputToContain('not usable yet')
        ->assertFailed()
        ->run();

    expect(envContents())->toContain('AWS_USE_PATH_STYLE_ENDPOINT=true');
});

test('it refuses an unknown backend without editing the env file', function () {
    $before = envContents();

    $this->artisan('storage:configure', ['--backend' => 'dropbox'])->assertFailed()->run();

    expect(envContents())->toBe($before);
});

test('it offers to copy files that are still on the other backend', function () {
    File::put($this->storageDirectory.'/app/public/.gitignore', "*\n");
    File::ensureDirectoryExists($this->storageDirectory.'/app/public/media');
    File::put($this->storageDirectory.'/app/public/media/hero.webp', 'bytes');

    $this->artisan('storage:configure', ['--backend' => 's3', '--skip-verify' => true])
        ->expectsChoice('Which S3-compatible service?', 'aws', [
            'aws' => 'AWS S3',
            'r2' => 'Cloudflare R2',
            'other' => 'Something else (MinIO, DigitalOcean Spaces, …)',
        ])
        ->expectsQuestion('Access key ID', 'access-key')
        ->expectsQuestion('Secret access key', 'secret-key')
        ->expectsQuestion('Region', 'us-east-1')
        ->expectsQuestion('Bucket for private files (attachments, pending uploads)', 'maildun-private')
        ->expectsQuestion('Bucket for public assets (media, logos, avatars)', 'maildun-public')
        ->expectsQuestion('Public base URL for assets, without a trailing slash (blank to use the bucket URL)', '')
        ->expectsOutputToContain('1 file(s) are still on the local filesystem')
        ->expectsConfirmation('Copy them to AWS S3 now?', 'no')
        ->expectsOutputToContain('storage:sync --to=s3')
        ->assertSuccessful()
        ->run();

    expect(envContents())
        ->toContain('OBJECT_STORAGE_PROVIDER=aws')
        ->toContain('AWS_ACCESS_KEY_ID=access-key')
        ->toContain('AWS_BUCKET=maildun-private')
        ->toContain('AWS_PUBLIC_BUCKET=maildun-public')
        ->toContain('AWS_USE_PATH_STYLE_ENDPOINT=false')
        ->not->toContain('R2_ACCESS_KEY_ID=access-key');
});

test('gitignore stubs on a fresh install are not reported as files to copy', function () {
    File::put($this->storageDirectory.'/app/public/.gitignore', "*\n");
    File::put($this->storageDirectory.'/app/private/.gitignore', "*\n");

    $this->artisan('storage:configure', ['--backend' => 'local', '--skip-verify' => true])
        ->doesntExpectOutputToContain('still on the')
        ->assertSuccessful()
        ->run();
});
