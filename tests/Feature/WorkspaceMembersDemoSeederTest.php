<?php

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\WorkspaceMembersDemoSeeder;

test('the workspace members demo seed is complete and idempotent', function () {
    $this->seed(WorkspaceMembersDemoSeeder::class);
    $this->seed(WorkspaceMembersDemoSeeder::class);

    $team = Team::query()->where('slug', 'maildun-studio')->sole();
    $owner = User::query()->where('email', AdminUserSeeder::DEMO_EMAIL)->sole();

    expect($team->members()->count())->toBe(25)
        ->and($team->memberships()->where('role', TeamRole::Admin->value)->count())->toBe(3)
        ->and($team->invitations()->whereNull('accepted_at')->count())->toBe(9)
        ->and($owner->teamRole($team))->toBe(TeamRole::Owner->value)
        ->and($team->invitations()->where('email', 'grace@invitation-demo.test')->sole()->role)
        ->toBe(TeamRole::Admin->value);
});
