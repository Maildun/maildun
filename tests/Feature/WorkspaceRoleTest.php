<?php

use App\Actions\Teams\CreateTeam;
use App\Enums\TeamPermission;
use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use App\Models\WorkspaceRole;
use Inertia\Testing\AssertableInertia as Assert;

test('new workspaces receive one protected owner and the admin and member defaults', function () {
    $owner = User::factory()->create();

    $team = app(CreateTeam::class)->handle($owner, 'Role settings');

    expect($team->workspaceRoles()->orderBy('name')->pluck('name')->all())
        ->toEqual(['admin', 'member', 'owner'])
        ->and($team->memberships()->where('role', TeamRole::Owner->value)->count())->toBe(1);

    $this->actingAs($owner)
        ->get(route('teams.roles.index', $team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('teams/roles')
            ->has('roles', 3)
            ->where('roles.0.name', 'owner')
            ->where('roles.1.name', 'admin')
            ->where('roles.2.name', 'member'));
});

test('owners can create custom workspace roles with selected permissions', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $this->actingAs($owner)
        ->post(route('teams.roles.store', $team), [
            'name' => 'Campaign manager',
            'permissions' => [
                TeamPermission::ManageAudience->value,
                TeamPermission::ManageCampaign->value,
            ],
        ])
        ->assertRedirect(route('teams.roles.index', $team));

    $role = $team->workspaceRoles()->where('name', 'campaign-manager')->firstOrFail();

    expect($role->label)->toBe('Campaign manager')
        ->and($role->is_system)->toBeFalse();

    $team->memberships()
        ->where('user_id', $member->id)
        ->firstOrFail()
        ->update(['role' => $role->name]);

    expect($member->hasTeamPermission($team, TeamPermission::ManageAudience))->toBeTrue()
        ->and($member->hasTeamPermission($team, TeamPermission::ManageCampaign))->toBeTrue()
        ->and($member->hasTeamPermission($team, TeamPermission::DeleteTeam))->toBeFalse();
});

test('a role granted only one split permission cannot manage a sibling feature', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $this->actingAs($owner)
        ->post(route('teams.roles.store', $team), [
            'name' => 'Contact manager',
            'permissions' => [TeamPermission::ManageContact->value],
        ])
        ->assertRedirect(route('teams.roles.index', $team));

    $role = $team->workspaceRoles()->where('name', 'contact-manager')->firstOrFail();

    $team->memberships()
        ->where('user_id', $member->id)
        ->firstOrFail()
        ->update(['role' => $role->name]);

    expect($member->hasTeamPermission($team, TeamPermission::ManageContact))->toBeTrue()
        ->and($member->hasTeamPermission($team, TeamPermission::ManageCompany))->toBeFalse()
        ->and($member->hasTeamPermission($team, TeamPermission::ManageAudience))->toBeFalse();
});

test('only the owner can manage roles and protected roles cannot be changed', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);

    $ownerRole = $team->workspaceRoles()->where('name', 'owner')->firstOrFail();

    $this->actingAs($admin)
        ->post(route('teams.roles.store', $team), [
            'name' => 'Support',
            'permissions' => [],
        ])
        ->assertForbidden();

    $this->actingAs($owner)
        ->patch(route('teams.roles.update', [$team, $ownerRole]), [
            'name' => 'Workspace owner',
            'permissions' => [],
        ])
        ->assertForbidden();
});

test('custom roles cannot be deleted while they are assigned in the workspace', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $role = WorkspaceRole::create([
        'team_id' => $team->id,
        'name' => 'support',
        'label' => 'Support',
        'guard_name' => 'web',
    ]);

    $team->memberships()
        ->where('user_id', $member->id)
        ->firstOrFail()
        ->update(['role' => $role->name]);

    $this->actingAs($owner)
        ->delete(route('teams.roles.destroy', [$team, $role]))
        ->assertStatus(422);

    expect($role->fresh())->not->toBeNull();
});
