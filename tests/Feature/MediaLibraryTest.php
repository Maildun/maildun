<?php

use App\Jobs\ProcessMediaImage;
use App\Models\Media;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

test('the dropzone accepts at most twenty files per upload', function () {
    Queue::fake();
    Storage::fake('public');

    $user = User::factory()->create();
    $team = $user->currentTeam;

    $this->actingAs($user)
        ->post(route('media.store', $team), [
            'files' => collect(range(1, 21))
                ->map(fn (int $number): UploadedFile => UploadedFile::fake()->image("image-{$number}.png"))
                ->all(),
        ])
        ->assertInvalid('files');

    expect($team->media()->count())->toBe(0);
});

test('processing and failed media expose their state to the detail dialog', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    Media::factory()->for($team)->processing()->create(['name' => 'processing.png', 'created_at' => now()->subMinute()]);
    Media::factory()->for($team)->failed()->create(['name' => 'failed.png', 'failed_reason' => 'Unable to convert the uploaded image to WebP.']);

    $this->actingAs($user)
        ->get(route('media.index', $team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('media.data', 2)
            ->where('media.data.0.name', 'failed.png')
            ->where('media.data.0.status', 'failed')
            ->where('media.data.0.processing', false)
            ->where('media.data.0.failed_reason', 'Unable to convert the uploaded image to WebP.')
            ->where('media.data.1.name', 'processing.png')
            ->where('media.data.1.status', 'processing')
            ->where('media.data.1.processing', true));
});

test('owners can clear the alt text and are told the media was updated', function () {
    $user = User::factory()->create();
    $media = Media::factory()->for($user->currentTeam)->create(['alt' => 'Spring sale banner']);

    $this->actingAs($user)
        ->patch(route('media.update', [$user->currentTeam, $media]), [
            'name' => $media->name,
            'alt' => null,
        ])
        ->assertRedirect()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Media updated.']);

    expect($media->fresh()->alt)->toBeNull();
});

test('media needs a name and an alt text of at most 255 characters', function () {
    $user = User::factory()->create();
    $media = Media::factory()->for($user->currentTeam)->create(['name' => 'hero.png', 'alt' => 'Hero']);

    $this->actingAs($user)
        ->patch(route('media.update', [$user->currentTeam, $media]), [
            'name' => '',
            'alt' => str_repeat('a', 256),
        ])
        ->assertInvalid(['name', 'alt']);

    expect($media->fresh())
        ->name->toBe('hero.png')
        ->alt->toBe('Hero');
});

test('deleting media that is still converting removes the pending upload and the queued conversion publishes nothing', function () {
    Storage::fake('local');
    Storage::fake('public');

    $user = User::factory()->create();
    $media = Media::factory()->for($user->currentTeam)->processing()->create();
    $sourcePath = $media->upload_path;
    Storage::disk('local')->put($sourcePath, UploadedFile::fake()->image('hero.png')->getContent());

    $this->actingAs($user)
        ->delete(route('media.destroy', [$user->currentTeam, $media]))
        ->assertRedirect()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Media deleted.']);

    $this->assertSoftDeleted($media);
    Storage::disk('local')->assertMissing($sourcePath);

    (new ProcessMediaImage($media->id, $sourcePath, 'local'))->handle();

    expect(Storage::disk('public')->allFiles())->toBe([]);
});

test('media settings require a boolean convert switch', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $this->actingAs($user)
        ->patch(route('media.settings.update', $team), [
            'convert_uploads_to_webp' => 'sometimes',
        ])
        ->assertInvalid('convert_uploads_to_webp');

    expect($team->fresh()->convert_uploads_to_webp)->toBeFalse();
});

test('saving media settings confirms with a toast', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('media.settings.update', $user->currentTeam), [
            'convert_uploads_to_webp' => false,
        ])
        ->assertRedirect()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Media settings updated.']);
});
