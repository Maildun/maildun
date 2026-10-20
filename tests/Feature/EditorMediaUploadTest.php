<?php

use App\Enums\TeamRole;
use App\Jobs\ProcessMediaImage;
use App\Models\Media;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

test('an image block upload answers with the ready image URL', function () {
    Storage::fake('public');
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $this->actingAs($user)
        ->postJson(route('media.editor_uploads.store', $team), [
            'files' => [UploadedFile::fake()->image('hero.png')],
        ])
        ->assertCreated()
        ->assertJsonPath('media.name', 'hero.png')
        ->assertJsonPath('media.status', 'ready')
        ->assertJsonPath('media.absolute_url', fn (?string $url): bool => str_ends_with((string) $url, '.png'));

    expect($team->media()->count())->toBe(1);
});

test('a converting upload reports processing until the queue finishes it', function () {
    Queue::fake();
    Storage::fake('local');
    Storage::fake('public');
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $team->update(['convert_uploads_to_webp' => true]);

    $uuid = $this->actingAs($user)
        ->postJson(route('media.editor_uploads.store', $team), [
            'files' => [UploadedFile::fake()->image('hero.png')],
        ])
        ->assertCreated()
        ->assertJsonPath('media.status', 'processing')
        ->json('media.uuid');

    Queue::assertPushed(ProcessMediaImage::class);

    $this->actingAs($user)
        ->getJson(route('media.editor_uploads.show', [$team, $uuid]))
        ->assertOk()
        ->assertJsonPath('media.status', 'processing');
});

test('rejects files that are not images', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('media.editor_uploads.store', $user->currentTeam), [
            'files' => [UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf')],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['files.0']);
});

test('viewers cannot upload from the editor', function () {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($viewer, ['role' => TeamRole::Member->value]);
    assignViewerRole($team, $viewer);

    $this->actingAs($viewer)
        ->postJson(route('media.editor_uploads.store', $team), [
            'files' => [UploadedFile::fake()->image('hero.png')],
        ])
        ->assertForbidden();

    expect($team->media()->count())->toBe(0);
});

test('media of another workspace cannot be polled', function () {
    $user = User::factory()->create();
    $media = Media::factory()->create();

    $this->actingAs($user)
        ->getJson(route('media.editor_uploads.show', [$user->currentTeam, $media]))
        ->assertNotFound();
});
