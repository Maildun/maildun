<?php

use App\Enums\TeamPermission;
use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;

test('permissions are isolated by team', function () {
    $user = User::factory()->create();
    $personalTeam = $user->personalTeam();
    $memberTeam = Team::factory()->create();

    $memberTeam->members()->attach($user, ['role' => TeamRole::Member->value]);

    expect($user->hasTeamPermission($personalTeam, TeamPermission::UpdateTeam))->toBeTrue()
        ->and($user->hasTeamPermission($memberTeam, TeamPermission::UpdateTeam))->toBeFalse();

    $this->assertDatabaseHas('roles', [
        'team_id' => $personalTeam->id,
        'name' => TeamRole::Owner->value,
        'guard_name' => 'web',
    ]);

    $this->assertDatabaseHas('model_has_roles', [
        'team_id' => $memberTeam->id,
        'model_id' => $user->id,
        'model_type' => User::class,
    ]);
});

test('changing a membership role changes its team permissions', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($user, ['role' => TeamRole::Member->value]);

    expect($user->hasTeamPermission($team, TeamPermission::UpdateTeam))->toBeFalse();

    $team->memberships()
        ->where('user_id', $user->id)
        ->firstOrFail()
        ->update(['role' => TeamRole::Admin]);

    expect($user->hasTeamPermission($team, TeamPermission::UpdateTeam))->toBeTrue()
        ->and($user->hasTeamPermission($team, TeamPermission::DeleteTeam))->toBeTrue();
});

test('media management is granted to owners, admins, and members', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    expect($owner->hasTeamPermission($team, TeamPermission::ManageMedia))->toBeTrue()
        ->and($admin->hasTeamPermission($team, TeamPermission::ManageMedia))->toBeTrue()
        ->and($member->hasTeamPermission($team, TeamPermission::ManageMedia))->toBeTrue();
});

test('starter roles provision the requested permission sets', function () {
    $team = Team::factory()->create();
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $member = User::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $memberPermissions = [
        TeamPermission::ManageContact,
        TeamPermission::ManageAudience,
        TeamPermission::ManageCompany,
        TeamPermission::ManageTag,
        TeamPermission::ManageCampaign,
        TeamPermission::ManageTransactional,
        TeamPermission::ManageAutomation,
        TeamPermission::ManageTemplate,
        TeamPermission::ManageMedia,
    ];

    foreach ([
        [$owner, TeamPermission::cases()],
        [$admin, TeamPermission::cases()],
        [$member, $memberPermissions],
    ] as [$user, $permissions]) {
        foreach (TeamPermission::cases() as $permission) {
            expect($user->hasTeamPermission($team, $permission))
                ->toBe(in_array($permission, $permissions, true));
        }
    }
});

test('audience management is granted to owners, admins, and members', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    expect($owner->hasTeamPermission($team, TeamPermission::ManageAudience))->toBeTrue()
        ->and($admin->hasTeamPermission($team, TeamPermission::ManageAudience))->toBeTrue()
        ->and($member->hasTeamPermission($team, TeamPermission::ManageAudience))->toBeTrue();
});

test('changing a membership role re-synchronizes all permissions', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();

    $team->members()->attach($user, ['role' => TeamRole::Member->value]);

    expect($user->hasTeamPermission($team, TeamPermission::ManageAudience))->toBeTrue()
        ->and($user->hasTeamPermission($team, TeamPermission::ManageCampaign))->toBeTrue()
        ->and($user->hasTeamPermission($team, TeamPermission::ManageMedia))->toBeTrue();

    $team->memberships()
        ->where('user_id', $user->id)
        ->firstOrFail()
        ->update(['role' => TeamRole::Owner]);

    foreach (TeamPermission::cases() as $permission) {
        expect($user->hasTeamPermission($team, $permission))->toBeTrue();
    }
});
