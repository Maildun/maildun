<?php

use App\Enums\TeamPermission;
use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Spatie\Permission\Models\Permission;

test('strips team deletion from stored admin roles and keeps member management', function () {
    $team = Team::factory()->create();
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);
    $team->workspaceRoles()
        ->where('name', TeamRole::Admin->value)
        ->sole()
        ->givePermissionTo(Permission::findOrCreate(TeamPermission::DeleteTeam->value, 'web'));

    (require base_path('database/migrations/2026_11_08_101457_revoke_team_delete_permission_from_admin_roles.php'))->up();

    expect($admin->hasTeamPermission($team, TeamPermission::DeleteTeam))->toBeFalse()
        ->and($admin->hasTeamPermission($team, TeamPermission::UpdateMember))->toBeTrue()
        ->and($admin->hasTeamPermission($team, TeamPermission::RemoveMember))->toBeTrue()
        ->and($admin->hasTeamPermission($team, TeamPermission::CreateInvitation))->toBeTrue()
        ->and($owner->hasTeamPermission($team, TeamPermission::DeleteTeam))->toBeTrue();
});
