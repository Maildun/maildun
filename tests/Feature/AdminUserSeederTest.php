<?php

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Storage;

test('the database seeder creates an admin membership and is idempotent', function () {
    Storage::fake('public');

    $this->seed(DatabaseSeeder::class);
    $this->seed(DatabaseSeeder::class);

    $user = User::query()->where('email', AdminUserSeeder::DEMO_EMAIL)->sole();
    $team = $user->personalTeam();
    $demoTeam = Team::query()->where('slug', 'maildun-studio')->sole();

    $this->assertModelExists($user);

    expect($user->name)->toBe('Demo Administrator')
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($team)->not->toBeNull()
        ->and($team->slug)->not->toBeEmpty()
        ->and($user->fresh()->current_team_id)->toBe($demoTeam->id)
        ->and($user->teamRole($team))->toBe(TeamRole::Admin->value)
        ->and($team->memberships()->where('user_id', $user->id)->count())->toBe(1);
});

test('the demo seeder reuses an existing administrator without creating a predictable account', function () {
    $administrator = User::factory()->create();

    $this->seed(AdminUserSeeder::class);

    expect(User::query()->count())->toBe(1)
        ->and(User::query()->where('email', AdminUserSeeder::DEMO_EMAIL)->exists())->toBeFalse()
        ->and($administrator->fresh()->personalTeam())->not->toBeNull();
});
