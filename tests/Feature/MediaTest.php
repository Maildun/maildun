<?php

use App\Enums\MediaStatus;
use App\Enums\TeamRole;
use App\Jobs\ProcessMediaImage;
use App\Models\Media;
use App\Models\MediaCategory;
use App\Models\MediaTag;
use App\Models\Tag;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

test('the list shows the team media', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    Media::factory()->for($team)->create(['name' => 'hero.png']);
    Media::factory()->for(Team::factory()->create())->create(['name' => 'other.png']);

    $this->actingAs($user)
        ->get(route('media.index', $team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('media/index')
            ->where('canManage', true)
            ->where('convertUploadsToWebp', false)
            ->has('media.data', 1)
            ->where('media.data.0.name', 'hero.png')
            ->where('media.data.0.status', 'ready')
            ->where('media.data.0.category', null)
            ->where('media.data.0.tags', [])
            ->where('filters.category', '')
            ->where('filters.tag', '')
            ->has('categories', 0)
            ->has('tags', 0));
});

test('members can view media but cannot upload update or delete it', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    assignViewerRole($team, $member);
    $media = Media::factory()->for($team)->create(['name' => 'hero.png']);

    $this->actingAs($member)
        ->get(route('media.index', $team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('canManage', false));

    $this->actingAs($member)
        ->post(route('media.store', $team), [
            'files' => [UploadedFile::fake()->image('nope.png')],
        ])
        ->assertForbidden();

    $this->actingAs($member)
        ->patch(route('media.update', [$team, $media]), [
            'name' => 'Nope',
            'alt' => 'Nope',
        ])
        ->assertForbidden();

    $this->actingAs($member)
        ->delete(route('media.destroy', [$team, $media]))
        ->assertForbidden();
});

test('media cannot be reached through another team', function () {
    $user = User::factory()->create();
    $otherTeam = Team::factory()->create();
    $otherTeam->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $media = Media::factory()->for($user->currentTeam)->create();

    $this->actingAs($user)
        ->patch(route('media.update', [$otherTeam, $media]), [
            'name' => 'Nope',
            'alt' => null,
        ])
        ->assertNotFound();
});

test('owners can upload images without converting them', function () {
    Queue::fake();
    Storage::fake('public');

    $user = User::factory()->create();
    $team = $user->currentTeam;

    $this->actingAs($user)
        ->post(route('media.store', $team), [
            'files' => [UploadedFile::fake()->image('hero.png', 800, 600)->size(2048)],
        ])
        ->assertRedirect();

    $media = $team->media()->firstOrFail();

    expect($media->name)->toBe('hero.png')
        ->and($media->status)->toBe(MediaStatus::Ready)
        ->and($media->extension)->toBe('png')
        ->and($media->path)->toEndWith('.png')
        ->and($media->upload_path)->toBeNull()
        ->and($media->url)->toBe('/storage/'.$media->path);

    Storage::disk('public')->assertExists($media->path);
    Queue::assertNothingPushed();
});

test('owners can upload several files in one request', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $team = $user->currentTeam;

    $this->actingAs($user)
        ->post(route('media.store', $team), [
            'files' => [
                UploadedFile::fake()->image('one.png'),
                UploadedFile::fake()->image('two.jpg'),
            ],
        ])
        ->assertRedirect();

    expect($team->media()->count())->toBe(2)
        ->and($team->media()->orderBy('name')->pluck('name')->all())->toBe(['one.png', 'two.jpg']);
});

test('jpeg and png uploads convert to webp in the background when the setting is on', function () {
    Queue::fake();
    Storage::fake('local');
    Storage::fake('public');

    $user = User::factory()->create();
    $team = $user->currentTeam;
    $team->update(['convert_uploads_to_webp' => true]);

    $this->actingAs($user)
        ->post(route('media.store', $team), [
            'files' => [UploadedFile::fake()->image('hero.png', 1600, 900)],
        ])
        ->assertRedirect();

    $media = $team->media()->firstOrFail();
    $sourcePath = $media->upload_path;

    expect($media->status)->toBe(MediaStatus::Processing)
        ->and($sourcePath)->toStartWith('media/'.$team->uuid.'/pending/')
        ->and($media->path)->toBeNull();
    Storage::disk('local')->assertExists($sourcePath);

    Queue::assertPushed(ProcessMediaImage::class, function (ProcessMediaImage $job) use ($media, $sourcePath): bool {
        return $job->mediaId === $media->id && $job->sourcePath === $sourcePath;
    });

    (new ProcessMediaImage($media->id, $sourcePath))->handle();

    $media->refresh();

    expect($media->status)->toBe(MediaStatus::Ready)
        ->and($media->upload_path)->toBeNull()
        ->and($media->path)->toEndWith('.webp')
        ->and($media->mime_type)->toBe('image/webp')
        ->and($media->extension)->toBe('webp')
        ->and($media->url)->toBe('/storage/'.$media->path);
    Storage::disk('local')->assertMissing($sourcePath);
    Storage::disk('public')->assertExists($media->path);
    expect(Storage::disk('public')->mimeType($media->path))->toBe('image/webp');
});

test('webp conversion can read from s3 compatible storage and publish to the public disk', function () {
    config()->set('filesystems.default', 's3');
    Queue::fake();
    Storage::fake('s3');
    Storage::fake('public', ['url' => 'https://assets.example.com']);

    $user = User::factory()->create();
    $team = $user->currentTeam;
    $team->update(['convert_uploads_to_webp' => true]);

    $this->actingAs($user)
        ->post(route('media.store', $team), [
            'files' => [UploadedFile::fake()->image('hero.png', 1600, 900)],
        ])
        ->assertRedirect();

    $media = $team->media()->firstOrFail();
    $sourcePath = $media->upload_path;

    expect($media->disk)->toBe('s3');
    Storage::disk('s3')->assertExists($sourcePath);

    Queue::assertPushed(ProcessMediaImage::class, function (ProcessMediaImage $job) use ($media, $sourcePath): bool {
        return $job->mediaId === $media->id
            && $job->sourcePath === $sourcePath
            && $job->sourceDisk === 's3';
    });

    (new ProcessMediaImage($media->id, $sourcePath, 's3'))->handle();

    $media->refresh();

    expect($media->disk)->toBe('public')
        ->and($media->upload_path)->toBeNull()
        ->and($media->path)->toEndWith('.webp')
        ->and($media->url)->toBe('https://assets.example.com/'.$media->path)
        ->and($media->absolute_url)->toBe($media->url);
    Storage::disk('s3')->assertMissing($sourcePath);
    Storage::disk('public')->assertExists($media->path);
    expect(Storage::disk('public')->mimeType($media->path))->toBe('image/webp');
});

test('gif and webp uploads are not converted when the setting is on', function () {
    Queue::fake();
    Storage::fake('public');

    $user = User::factory()->create();
    $team = $user->currentTeam;
    $team->update(['convert_uploads_to_webp' => true]);

    $this->actingAs($user)
        ->post(route('media.store', $team), [
            'files' => [
                UploadedFile::fake()->image('loop.gif'),
                UploadedFile::fake()->image('already.webp'),
            ],
        ])
        ->assertRedirect();

    expect($team->media()->pluck('extension')->sort()->values()->all())->toBe(['gif', 'webp'])
        ->and($team->media->every(fn (Media $media): bool => $media->status === MediaStatus::Ready))->toBeTrue();
    Queue::assertNothingPushed();
});

test('a stale media conversion cannot replace a newer upload', function () {
    Storage::fake('local');
    Storage::fake('public');

    $media = Media::factory()->processing()->create();
    $staleSourcePath = 'media/stale.png';
    Storage::disk('local')->put($staleSourcePath, UploadedFile::fake()->image('stale.png')->getContent());

    (new ProcessMediaImage($media->id, $staleSourcePath))->handle();

    expect($media->fresh()->upload_path)->toBe($media->upload_path)
        ->and($media->fresh()->status)->toBe(MediaStatus::Processing);
    Storage::disk('local')->assertMissing($staleSourcePath);
});

test('a failed conversion marks the media as failed and deletes the pending file', function () {
    Storage::fake('local');

    $media = Media::factory()->processing()->create();
    $sourcePath = $media->upload_path;
    Storage::disk('local')->put($sourcePath, 'image');

    (new ProcessMediaImage($media->id, $sourcePath))
        ->failed(new RuntimeException('Unable to convert the uploaded image to WebP.'));

    $media->refresh();

    expect($media->status)->toBe(MediaStatus::Failed)
        ->and($media->upload_path)->toBeNull()
        ->and($media->failed_reason)->toBe('Unable to convert the uploaded image to WebP.');
    Storage::disk('local')->assertMissing($sourcePath);
});

test('owners can update the name and alt text', function () {
    $user = User::factory()->create();
    $category = MediaCategory::factory()->for($user->currentTeam)->create(['name' => 'Logos']);
    $media = Media::factory()->for($user->currentTeam)->for($category, 'category')->create(['name' => 'hero.png']);

    $this->actingAs($user)
        ->patch(route('media.update', [$user->currentTeam, $media]), [
            'name' => 'spring-sale.png',
            'alt' => 'Spring sale banner',
        ])
        ->assertRedirect();

    expect($media->fresh())
        ->name->toBe('spring-sale.png')
        ->alt->toBe('Spring sale banner')
        ->media_category_id->toBe($category->id);
});

test('owners can delete media and its files', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $media = Media::factory()->for($user->currentTeam)->create([
        'path' => 'media/'.$user->currentTeam->uuid.'/hero.png',
    ]);
    Storage::disk('public')->put($media->path, 'image');

    $this->actingAs($user)
        ->delete(route('media.destroy', [$user->currentTeam, $media]))
        ->assertRedirect();

    $this->assertModelMissing($media);
    Storage::disk('public')->assertMissing('media/'.$user->currentTeam->uuid.'/hero.png');
});

test('the list can be searched by name', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    Media::factory()->for($team)->create(['name' => 'hero.png']);
    Media::factory()->for($team)->create(['name' => 'footer.jpg']);

    $this->actingAs($user)
        ->get(route('media.index', ['current_team' => $team, 'q' => 'hero']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('media.data', 1)
            ->where('media.data.0.name', 'hero.png')
            ->where('filters.q', 'hero'));
});

test('the list can be filtered by category and tag', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $otherTeam = Team::factory()->create();

    $logos = MediaCategory::factory()->for($team)->create(['name' => 'Logos']);
    $banners = MediaCategory::factory()->for($team)->create(['name' => 'Banners']);
    MediaCategory::factory()->for($otherTeam)->create(['name' => 'Secret']);

    $heroTag = MediaTag::factory()->for($team)->create(['name' => 'hero']);
    MediaTag::factory()->for($otherTeam)->create(['name' => 'other']);

    $logo = Media::factory()->for($team)->for($logos, 'category')->create(['name' => 'logo.png']);
    Media::factory()->for($team)->for($banners, 'category')->create(['name' => 'banner.png']);
    Media::factory()->for($team)->create(['name' => 'notes.png']);
    $logo->tags()->attach($heroTag);

    $this->actingAs($user)
        ->get(route('media.index', ['current_team' => $team, 'category' => $logos->uuid]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('media.data', 1)
            ->where('media.data.0.name', 'logo.png')
            ->where('media.data.0.category.name', 'Logos')
            ->where('filters.category', $logos->uuid)
            ->has('categories', 2)
            ->has('tags', 1)
            ->where('categories.0.name', 'Banners')
            ->where('tags.0.name', 'hero'));

    $this->actingAs($user)
        ->get(route('media.index', ['current_team' => $team, 'category' => 'uncategorized']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('media.data', 1)
            ->where('media.data.0.name', 'notes.png')
            ->where('filters.category', 'uncategorized'));

    $this->actingAs($user)
        ->get(route('media.index', ['current_team' => $team, 'tag' => $heroTag->uuid]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('media.data', 1)
            ->where('media.data.0.name', 'logo.png')
            ->where('media.data.0.tags.0.name', 'hero')
            ->where('filters.tag', $heroTag->uuid));
});

test('owners can set a category and tags on media', function () {
    $user = User::factory()->create();
    $media = Media::factory()->for($user->currentTeam)->create(['name' => 'hero.png']);

    $this->actingAs($user)
        ->patch(route('media.update', [$user->currentTeam, $media]), [
            'name' => 'spring-sale.png',
            'alt' => 'Spring sale banner',
            'category' => 'Campaigns',
            'tags' => ['hero', 'spring', 'Hero'],
        ])
        ->assertRedirect();

    $media->refresh()->load(['category', 'tags']);

    expect($media->name)->toBe('spring-sale.png')
        ->and($media->alt)->toBe('Spring sale banner')
        ->and($media->category?->name)->toBe('Campaigns')
        ->and($media->tags->pluck('name')->sort()->values()->all())->toBe(['hero', 'spring'])
        ->and($user->currentTeam->mediaCategories()->count())->toBe(1)
        ->and($user->currentTeam->mediaTags()->count())->toBe(2)
        ->and(Tag::query()->count())->toBe(0);
});

test('owners can clear a category and tags', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $category = MediaCategory::factory()->for($team)->create(['name' => 'Logos']);
    $tag = MediaTag::factory()->for($team)->create(['name' => 'hero']);
    $media = Media::factory()->for($team)->for($category, 'category')->create();
    $media->tags()->attach($tag);

    $this->actingAs($user)
        ->patch(route('media.update', [$team, $media]), [
            'name' => $media->name,
            'alt' => $media->alt,
            'category' => '',
            'tags' => [],
        ])
        ->assertRedirect();

    $media->refresh()->load(['category', 'tags']);

    expect($media->category)->toBeNull()
        ->and($media->tags)->toHaveCount(0)
        ->and($team->mediaCategories()->count())->toBe(1)
        ->and($team->mediaTags()->count())->toBe(1);
});

test('uploads require an image file', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('media.store', $user->currentTeam), [])
        ->assertInvalid('files');

    $this->actingAs($user)
        ->post(route('media.store', $user->currentTeam), [
            'files' => [UploadedFile::fake()->create('notes.txt', 10, 'text/plain')],
        ])
        ->assertInvalid('files.0');
});

test('uploads reject files that are too large', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->post(route('media.store', $user->currentTeam), [
            'files' => [UploadedFile::fake()->image('huge.png')->size(2049)],
        ]);

    $response->assertSessionHasErrors([
        'files.0' => 'Each image must be 2 MB or smaller.',
    ]);
    expect($user->currentTeam->media()->count())->toBe(0);
});
