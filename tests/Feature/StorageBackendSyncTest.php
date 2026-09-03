<?php

use App\Enums\StorageBackend;
use App\Jobs\ProcessMediaImage;
use App\Jobs\ProcessSubscribeFormImage;
use App\Models\Email;
use App\Models\EmailAttachment;
use App\Models\Media;
use App\Models\SubscribeForm;
use App\Models\Team;
use App\Services\StorageBackendMigrator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * The preflight reads the effective config rather than raw env so it stays
 * correct under config:cache, so tests fill in the same config keys. The disks
 * are faked, so these values are only read by the preflight itself.
 */
function withConfiguredS3(Closure $callback): mixed
{
    config()->set([
        'filesystems.object_storage_provider' => 'custom',
        'filesystems.disks.s3.key' => 'access-key',
        'filesystems.disks.s3.secret' => 'secret-key',
        'filesystems.disks.s3.region' => 'auto',
        'filesystems.disks.s3.bucket' => 'maildun-private',
        'filesystems.disks.s3_public.bucket' => 'maildun-public',
        'filesystems.disks.s3_public.url' => 'https://assets.example.com',
        'filesystems.disks.s3_public.endpoint' => 'https://account.r2.cloudflarestorage.com',
    ]);

    return $callback();
}

beforeEach(function () {
    config()->set([
        'filesystems.default' => 'local',
        'filesystems.disks.public' => config('filesystems.disks.local_public'),
    ]);

    Storage::fake('local');
    Storage::fake('local_public');
    Storage::fake('s3');
    Storage::fake('s3_public');
});

test('both backends are addressable regardless of which one is active', function () {
    expect(config('filesystems.disks'))
        ->toHaveKeys(['local', 's3', 'local_public', 's3_public', 'public']);

    expect(StorageBackend::Local->privateDisk())->toBe('local')
        ->and(StorageBackend::Local->publicDisk())->toBe('local_public')
        ->and(StorageBackend::S3->privateDisk())->toBe('s3')
        ->and(StorageBackend::S3->publicDisk())->toBe('s3_public')
        ->and(StorageBackend::Local->other())->toBe(StorageBackend::S3);
});

test('it copies private and public files onto the target backend', function () {
    Storage::disk('local')->put('email-attachments/team/report.pdf', 'invoice');
    Storage::disk('local_public')->put('media/team/hero.webp', 'hero-bytes');
    Storage::disk('local_public')->put('avatars/me.png', 'avatar-bytes');

    withConfiguredS3(fn () => $this->artisan('storage:sync', ['--to' => 's3'])->assertSuccessful()->run());

    Storage::disk('s3')->assertExists('email-attachments/team/report.pdf');
    Storage::disk('s3_public')->assertExists('media/team/hero.webp');
    Storage::disk('s3_public')->assertExists('avatars/me.png');
    expect(Storage::disk('s3_public')->get('media/team/hero.webp'))->toBe('hero-bytes');

    Storage::disk('local')->assertExists('email-attachments/team/report.pdf');
});

test('it repoints rows that name a concrete private disk and leaves ready media alone', function () {
    $team = Team::factory()->create();
    $email = Email::factory()->for($team)->create();

    $attachment = EmailAttachment::factory()->for($email)->create([
        'disk' => 'local',
        'path' => 'email-attachments/report.pdf',
    ]);
    $pending = Media::factory()->for($team)->create([
        'disk' => 'local',
        'path' => null,
        'upload_path' => 'media/team/pending/raw.png',
    ]);
    $ready = Media::factory()->for($team)->create([
        'disk' => 'public',
        'path' => 'media/team/hero.webp',
        'upload_path' => null,
    ]);

    withConfiguredS3(fn () => $this->artisan('storage:sync', ['--to' => 's3'])->assertSuccessful()->run());

    expect($attachment->fresh()->disk)->toBe('s3')
        ->and($pending->fresh()->disk)->toBe('s3')
        ->and($ready->fresh()->disk)->toBe('public');
});

test('it repoints subscribe form artwork that is still being converted', function () {
    $pending = SubscribeForm::factory()->create([
        'image_upload_path' => 'subscribe-form-images/pending/raw.png',
        'image_upload_disk' => 'local',
        'image_path' => null,
    ]);
    $converted = SubscribeForm::factory()->create([
        'image_upload_path' => null,
        'image_upload_disk' => null,
        'image_path' => 'subscribe-form-images/hero.webp',
    ]);

    withConfiguredS3(fn () => $this->artisan('storage:sync', ['--to' => 's3'])
        ->expectsOutputToContain('Processing form artwork repointed')
        ->assertSuccessful()
        ->run());

    expect($pending->fresh()->image_upload_disk)->toBe('s3')
        ->and($converted->fresh()->image_upload_disk)->toBeNull()
        ->and($converted->fresh()->image_path)->toBe('subscribe-form-images/hero.webp');
});

test('it skips files already on the target unless overwrite is passed', function () {
    Storage::disk('local_public')->put('avatars/me.png', 'new-bytes');
    Storage::disk('s3_public')->put('avatars/me.png', 'old-bytes');

    withConfiguredS3(fn () => $this->artisan('storage:sync', ['--to' => 's3'])->assertSuccessful()->run());
    expect(Storage::disk('s3_public')->get('avatars/me.png'))->toBe('old-bytes');

    withConfiguredS3(fn () => $this->artisan('storage:sync', ['--to' => 's3', '--overwrite' => true])->assertSuccessful()->run());
    expect(Storage::disk('s3_public')->get('avatars/me.png'))->toBe('new-bytes');
});

test('a dry run writes nothing and repoints nothing', function () {
    $team = Team::factory()->create();
    $email = Email::factory()->for($team)->create();
    $attachment = EmailAttachment::factory()->for($email)->create(['disk' => 'local']);
    Storage::disk('local_public')->put('avatars/me.png', 'avatar-bytes');

    withConfiguredS3(fn () => $this->artisan('storage:sync', ['--to' => 's3', '--dry-run' => true])->assertSuccessful()->run());

    Storage::disk('s3_public')->assertMissing('avatars/me.png');
    expect($attachment->fresh()->disk)->toBe('local');
});

test('it refuses to run until the target backend is configured', function () {
    config()->set('filesystems.disks.s3_public.bucket', null);

    $this->artisan('storage:sync', ['--to' => 's3'])
        ->expectsOutputToContain('AWS_PUBLIC_BUCKET')
        ->assertFailed()
        ->run();

    Storage::disk('s3_public')->assertDirectoryEmpty('/');
});

test('a custom endpoint makes a public base url mandatory', function () {
    config()->set([
        'filesystems.object_storage_provider' => 'custom',
        'filesystems.disks.s3.key' => 'access-key',
        'filesystems.disks.s3.secret' => 'secret-key',
        'filesystems.disks.s3.region' => 'auto',
        'filesystems.disks.s3.bucket' => 'maildun-private',
        'filesystems.disks.s3_public.bucket' => 'maildun-public',
        'filesystems.disks.s3_public.url' => null,
        'filesystems.disks.s3_public.endpoint' => 'https://account.r2.cloudflarestorage.com',
    ]);

    $this->artisan('storage:sync', ['--to' => 's3'])
        ->expectsOutputToContain('AWS_PUBLIC_URL')
        ->assertFailed()
        ->run();

    /* Plain AWS S3 serves its own bucket URL, so the same gap is allowed there. */
    config()->set('filesystems.disks.s3_public.endpoint', null);

    $this->artisan('storage:sync', ['--to' => 's3'])->assertSuccessful()->run();
});

test('R2 configuration reports dedicated missing environment keys', function () {
    config()->set([
        'filesystems.object_storage_provider' => 'r2',
        'filesystems.disks.s3.key' => 'access-key',
        'filesystems.disks.s3.secret' => 'secret-key',
        'filesystems.disks.s3.region' => 'auto',
        'filesystems.disks.s3.bucket' => 'maildun-private',
        'filesystems.disks.s3.endpoint' => null,
        'filesystems.disks.s3_public.bucket' => 'maildun-public',
        'filesystems.disks.s3_public.url' => null,
    ]);

    expect(app(StorageBackendMigrator::class)->missingConfiguration(StorageBackend::S3))
        ->toBe(['R2_ENDPOINT', 'R2_PUBLIC_URL']);
});

test('it rejects an unknown target backend', function () {
    $this->artisan('storage:sync', ['--to' => 'dropbox'])->assertFailed();
});

test('it reports uploads still mid conversion before the switch', function () {
    $team = Team::factory()->create();
    Media::factory()->for($team)->create([
        'disk' => 'local',
        'path' => null,
        'upload_path' => 'media/team/pending/raw.png',
    ]);
    SubscribeForm::factory()->create([
        'image_upload_path' => 'subscribe-form-images/pending/raw.png',
        'image_upload_disk' => 'local',
    ]);

    expect(app(StorageBackendMigrator::class)->inFlightUploads())->toBe(2);

    withConfiguredS3(fn () => $this->artisan('storage:sync', ['--to' => 's3'])
        ->expectsOutputToContain('still being converted')
        ->assertSuccessful()->run());
});

test('artwork queued before the switch converts from the new backend afterwards', function () {
    $sourcePath = 'subscribe-form-images/pending/hero.png';
    $subscribeForm = SubscribeForm::factory()->create([
        'image_upload_path' => $sourcePath,
        'image_upload_disk' => 'local',
        'image_path' => null,
    ]);
    Storage::disk('local')->put($sourcePath, UploadedFile::fake()->image('hero.png', 400, 300)->getContent());

    withConfiguredS3(fn () => $this->artisan('storage:sync', ['--to' => 's3'])->assertSuccessful()->run());

    /* Drop the old copy so the job can only succeed by following the repointed
     * row to s3 rather than reading its own captured disk. */
    Storage::disk('local')->delete($sourcePath);

    /* The job was dispatched while local was still active, so it carries "local". */
    (new ProcessSubscribeFormImage($subscribeForm->id, $sourcePath, 'local'))->handle();

    $subscribeForm->refresh();

    expect($subscribeForm->image_path)->toEndWith('.webp')
        ->and($subscribeForm->image_upload_path)->toBeNull()
        ->and($subscribeForm->image_upload_disk)->toBeNull();

    Storage::disk('public')->assertExists($subscribeForm->image_path);
    Storage::disk('s3')->assertMissing($sourcePath);
});

test('media queued before the switch converts from the new backend afterwards', function () {
    $team = Team::factory()->create();
    $media = Media::factory()->for($team)->create([
        'disk' => 'local',
        'path' => null,
        'upload_path' => 'media/'.$team->uuid.'/pending/hero.png',
        'extension' => 'png',
        'mime_type' => 'image/png',
    ]);
    Storage::disk('local')->put($media->upload_path, UploadedFile::fake()->image('hero.png', 400, 300)->getContent());

    withConfiguredS3(fn () => $this->artisan('storage:sync', ['--to' => 's3'])->assertSuccessful()->run());

    Storage::disk('local')->delete($media->upload_path);

    (new ProcessMediaImage($media->id, $media->upload_path, 'local'))->handle();

    $fresh = $media->fresh();

    expect($fresh->disk)->toBe('public')
        ->and($fresh->path)->toEndWith('.webp')
        ->and($fresh->upload_path)->toBeNull();

    Storage::disk('public')->assertExists($fresh->path);
});
