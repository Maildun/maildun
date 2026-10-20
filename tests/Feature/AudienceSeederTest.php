<?php

use App\Enums\SubscriberSource;
use App\Enums\SubscriberStatus;
use App\Enums\TeamRole;
use App\Models\Audience;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\AudienceSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\SubscriberSeeder;
use Illuminate\Support\Facades\Storage;

test('the database seeder fills audience and subscriber lists and is idempotent', function () {
    Storage::fake('public');

    $this->seed(DatabaseSeeder::class);
    $this->seed(DatabaseSeeder::class);

    $user = User::query()->where('email', AdminUserSeeder::DEMO_EMAIL)->sole();
    $team = $user->personalTeam();

    expect($team)->not->toBeNull()
        ->and($team->audiences()->count())->toBe(5)
        ->and($team->tags()->count())->toBe(4)
        ->and($team->audiences()->doesntHave('subscribers')->count())->toBe(1)
        ->and($team->audiences()->has('segments')->count())->toBe(2)
        ->and($team->audiences()->has('subscribeForms')->count())->toBe(2);

    $newsletter = $team->audiences()->where('name', 'Product newsletter')->sole();
    $empty = $team->audiences()->where('name', 'Internal testers')->sole();

    expect($newsletter->subscribers()->count())->toBe(60)
        ->and($newsletter->subscribers()->where('status', SubscriberStatus::Subscribed)->count())->toBe(48)
        ->and($newsletter->subscribers()->where('status', SubscriberStatus::Unsubscribed)->count())->toBe(12)
        ->and($newsletter->subscribers()->where('source', SubscriberSource::Form)->count())->toBeGreaterThan(0)
        ->and($newsletter->subscribers()->where('source', SubscriberSource::Manual)->count())->toBeGreaterThan(0)
        ->and($newsletter->subscribers()->where('created_at', '<', now()->subDays(28))->count())->toBeGreaterThan(0)
        ->and($newsletter->subscribers()->whereHas('tags')->count())->toBeGreaterThan(0)
        ->and($newsletter->subscribeForms()->count())->toBe(1)
        ->and($newsletter->segments()->count())->toBe(1)
        ->and($empty->subscribers()->count())->toBe(0)
        ->and(Audience::query()->whereBelongsTo($team)->count())->toBe(5);
});

test('the audience demo data goes to the administrator\'s own personal workspace, not one they only joined', function () {
    $administrator = User::factory()->create();
    $administrator->personalTeam()->delete();
    $foreignTeam = User::factory()->create()->personalTeam();
    $foreignTeam->memberships()->create([
        'user_id' => $administrator->id,
        'role' => TeamRole::Member->value,
    ]);

    $this->seed([AdminUserSeeder::class, AudienceSeeder::class, SubscriberSeeder::class]);

    $ownedTeam = $administrator->ownedTeams()->where('teams.is_personal', true)->sole();

    expect($ownedTeam->id)->not->toBe($foreignTeam->id)
        ->and($ownedTeam->audiences()->count())->toBe(5)
        ->and($ownedTeam->tags()->count())->toBe(4)
        ->and($ownedTeam->audiences()->where('name', 'Product newsletter')->sole()->subscribers()->count())->toBe(60)
        ->and($foreignTeam->audiences()->exists())->toBeFalse()
        ->and($foreignTeam->tags()->exists())->toBeFalse();
});
