<?php

use App\Models\Media;
use App\Models\SubscribeForm;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/**
 * Every stored file is displayed through the logical "public" disk, so the same
 * accessors must produce host-relative URLs on local storage and absolute CDN
 * URLs on S3-compatible storage without any model change.
 */
function useS3PublicDisk(): void
{
    config()->set('filesystems.disks.public', [
        'driver' => 's3',
        'key' => 'access-key',
        'secret' => 'secret-key',
        'region' => 'auto',
        'bucket' => 'maildun-public',
        'url' => 'https://assets.example.com',
        'endpoint' => 'https://account.r2.cloudflarestorage.com',
        'use_path_style_endpoint' => false,
        'throw' => false,
        'report' => false,
    ]);

    Storage::forgetDisk('public');
}

afterEach(fn () => Storage::forgetDisk('public'));

test('local storage displays every uploaded file on the current host', function () {
    $team = Team::factory()->create(['logo_path' => 'team-logos/logo.png']);
    $user = User::factory()->create(['avatar_path' => 'avatars/me.png']);
    $media = Media::factory()->for($team)->create(['disk' => 'public', 'path' => 'media/t/hero.webp']);
    $form = SubscribeForm::factory()->create([
        'logo_path' => 'subscribe-form-logos/logo.png',
        'image_path' => 'subscribe-form-images/hero.webp',
    ]);

    expect($team->logo)->toBe('/storage/team-logos/logo.png')
        ->and($user->avatar)->toBe('/storage/avatars/me.png')
        ->and($media->url)->toBe('/storage/media/t/hero.webp')
        ->and($form->logo)->toBe('/storage/subscribe-form-logos/logo.png')
        ->and($form->image)->toBe('/storage/subscribe-form-images/hero.webp');

    expect($media->absolute_url)
        ->toStartWith('http')
        ->toEndWith('/storage/media/t/hero.webp');
});

test('s3 storage displays every uploaded file on the public bucket url', function () {
    $team = Team::factory()->create(['logo_path' => 'team-logos/logo.png']);
    $user = User::factory()->create(['avatar_path' => 'avatars/me.png']);
    $media = Media::factory()->for($team)->create(['disk' => 'public', 'path' => 'media/t/hero.webp']);
    $form = SubscribeForm::factory()->create([
        'logo_path' => 'subscribe-form-logos/logo.png',
        'image_path' => 'subscribe-form-images/hero.webp',
    ]);

    useS3PublicDisk();

    expect($team->logo)->toBe('https://assets.example.com/team-logos/logo.png')
        ->and($user->avatar)->toBe('https://assets.example.com/avatars/me.png')
        ->and($media->url)->toBe('https://assets.example.com/media/t/hero.webp')
        ->and($form->logo)->toBe('https://assets.example.com/subscribe-form-logos/logo.png')
        ->and($form->image)->toBe('https://assets.example.com/subscribe-form-images/hero.webp');
});

test('an absolute media url is passed through untouched on s3 instead of being prefixed', function () {
    $media = Media::factory()->create(['disk' => 'public', 'path' => 'media/t/hero.webp']);

    useS3PublicDisk();

    expect($media->absolute_url)->toBe('https://assets.example.com/media/t/hero.webp');
});

test('generated avatars stay on the app host on both backends', function () {
    $team = Team::factory()->create(['logo_path' => null]);
    $user = User::factory()->create(['avatar_path' => null]);

    expect($team->logo)->toStartWith('/avatars/')
        ->and($user->avatar)->toStartWith('/avatars/');

    useS3PublicDisk();

    expect($team->fresh()->logo)->toStartWith('/avatars/')
        ->and($user->fresh()->avatar)->toStartWith('/avatars/');
});
