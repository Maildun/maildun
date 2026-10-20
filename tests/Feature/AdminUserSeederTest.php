<?php

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Storage;

test('the database seeder makes the administrator owner of their personal workspace and is idempotent', function () {
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
        ->and($user->teamRole($team))->toBe(TeamRole::Owner->value)
        ->and($team->memberships()->where('user_id', $user->id)->count())->toBe(1);
});

test('the demo seeder reuses an existing administrator without creating a predictable account', function () {
    $administrator = User::factory()->create();

    $this->seed(AdminUserSeeder::class);

    expect(User::query()->count())->toBe(1)
        ->and(User::query()->where('email', AdminUserSeeder::DEMO_EMAIL)->exists())->toBeFalse()
        ->and($administrator->fresh()->personalTeam())->not->toBeNull();
});

test('the demo seeder keeps a reused administrator as owner of their personal workspace', function () {
    $administrator = User::factory()->create();
    $personalTeam = $administrator->personalTeam();

    $this->seed(AdminUserSeeder::class);

    expect($administrator->teamRole($personalTeam))->toBe(TeamRole::Owner->value)
        ->and($personalTeam->memberships()->count())->toBe(1)
        ->and($administrator->fresh()->current_team_id)->toBe($personalTeam->id);
});

test('the demo seeder restores the owner of a personal workspace an earlier seed demoted', function () {
    $administrator = User::factory()->create();
    $personalTeam = $administrator->personalTeam();
    $personalTeam->memberships()->sole()->update(['role' => TeamRole::Admin->value]);

    $this->seed(AdminUserSeeder::class);

    expect($administrator->teamRole($personalTeam))->toBe(TeamRole::Owner->value)
        ->and($personalTeam->memberships()->count())->toBe(1);
});

test('the demo seeder never makes the administrator an owner of another user\'s personal workspace', function () {
    $administrator = User::factory()->create();
    $administrator->personalTeam()->delete();
    $workspaceOwner = User::factory()->create();
    $foreignTeam = $workspaceOwner->personalTeam();
    $foreignTeam->memberships()->create([
        'user_id' => $administrator->id,
        'role' => TeamRole::Member->value,
    ]);

    $this->seed(AdminUserSeeder::class);

    $ownedTeam = $administrator->ownedTeams()->where('teams.is_personal', true)->sole();

    expect($foreignTeam->memberships()->where('role', TeamRole::Owner->value)->sole()->user_id)->toBe($workspaceOwner->id)
        ->and($administrator->teamRole($foreignTeam))->toBe(TeamRole::Member->value);
    expect($ownedTeam->id)->not->toBe($foreignTeam->id)
        ->and($administrator->fresh()->current_team_id)->toBe($ownedTeam->id);
});
