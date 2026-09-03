<?php

use App\Enums\StorageBackend;
use App\Services\StorageBackendMigrator;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\mock;

test('it checks the current storage without interaction and removes probe files', function () {
    Storage::fake('local');
    Storage::fake('local_public');
    config()->set('filesystems.default', 'local');

    $this->artisan('storage:check', ['--no-interaction' => true])
        ->expectsOutputToContain('Checking local filesystem')
        ->expectsOutputToContain('Temporary files were written, read back, and removed')
        ->assertSuccessful()
        ->run();

    Storage::disk('local')->assertDirectoryEmpty('/');
    Storage::disk('local_public')->assertDirectoryEmpty('/');
});

test('it fails before probing when the effective configuration is incomplete', function () {
    config()->set('filesystems.default', 's3');
    config()->set([
        'filesystems.object_storage_provider' => 'r2',
        'filesystems.disks.s3.key' => null,
        'filesystems.disks.s3.secret' => null,
        'filesystems.disks.s3.region' => 'auto',
        'filesystems.disks.s3.bucket' => 'maildun-private',
        'filesystems.disks.s3.endpoint' => 'https://account.r2.cloudflarestorage.com',
        'filesystems.disks.s3_public.bucket' => 'maildun-public',
        'filesystems.disks.s3_public.url' => 'https://assets.example.com',
    ]);

    $this->artisan('storage:check', ['--no-interaction' => true])
        ->expectsOutputToContain('Storage configuration is incomplete')
        ->expectsOutputToContain('R2_ACCESS_KEY_ID')
        ->assertFailed()
        ->run();
});

test('it returns a failure exit code without exposing storage credentials', function () {
    $accessKey = 'sensitive-access-key';
    $secretKey = 'sensitive-secret-key';
    config()->set('filesystems.default', 's3');
    config()->set([
        'filesystems.object_storage_provider' => 'r2',
        'filesystems.disks.s3.key' => $accessKey,
        'filesystems.disks.s3.secret' => $secretKey,
    ]);
    $migrator = mock(StorageBackendMigrator::class);
    $migrator->shouldReceive('missingConfiguration')
        ->once()
        ->with(StorageBackend::S3)
        ->andReturn([]);
    $migrator->shouldReceive('probe')
        ->once()
        ->with(StorageBackend::S3)
        ->andReturn(['s3' => "AccessDenied for {$accessKey} using {$secretKey}"]);

    $this->artisan('storage:check', ['--no-interaction' => true])
        ->expectsOutputToContain('Storage check failed')
        ->expectsOutputToContain('AccessDenied for [redacted] using [redacted]')
        ->doesntExpectOutputToContain($accessKey)
        ->doesntExpectOutputToContain($secretKey)
        ->assertFailed()
        ->run();
});
