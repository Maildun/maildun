<?php

use App\Enums\TeamPermission;
use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;

function gatedWorkspaceActions(): array
{
    return [
        'audience:manage' => [TeamPermission::ManageAudience, fn (Team $team, User $other): array => ['post', route('audiences.store', $team), []]],
        'contact:manage' => [TeamPermission::ManageContact, fn (Team $team, User $other): array => ['post', route('contacts.store', $team), []]],
        'company:manage' => [TeamPermission::ManageCompany, fn (Team $team, User $other): array => ['post', route('companies.store', $team), []]],
        'tag:manage' => [TeamPermission::ManageTag, fn (Team $team, User $other): array => ['post', route('tags.store', $team), []]],
        'media:manage' => [TeamPermission::ManageMedia, fn (Team $team, User $other): array => ['post', route('media.store', $team), []]],
        'campaign:manage' => [TeamPermission::ManageCampaign, fn (Team $team, User $other): array => ['post', route('emails.store', $team), []]],
        'automation:manage' => [TeamPermission::ManageAutomation, fn (Team $team, User $other): array => ['post', route('automations.store', $team), []]],
        'transactional:manage' => [TeamPermission::ManageTransactional, fn (Team $team, User $other): array => ['post', route('transactional_emails.store', $team), []]],
        'template:manage' => [TeamPermission::ManageTemplate, fn (Team $team, User $other): array => ['post', route('email_templates.store', $team), []]],
        'team:update' => [TeamPermission::UpdateTeam, fn (Team $team, User $other): array => ['patch', route('teams.update', $team), ['name' => 'Renamed workspace']]],
        'team:delete' => [TeamPermission::DeleteTeam, fn (Team $team, User $other): array => ['delete', route('teams.destroy', $team), ['name' => $team->name]]],
        'member:update' => [TeamPermission::UpdateMember, fn (Team $team, User $other): array => ['patch', route('teams.members.update', [$team, $other]), ['role' => TeamRole::Admin->value]]],
        'member:remove' => [TeamPermission::RemoveMember, fn (Team $team, User $other): array => ['delete', route('teams.members.destroy', [$team, $other]), []]],
        'invitation:create' => [TeamPermission::CreateInvitation, fn (Team $team, User $other): array => ['post', route('teams.invitations.store', $team), ['email' => 'invitee@example.com', 'role' => TeamRole::Member->value]]],
        'invitation:cancel' => [TeamPermission::CancelInvitation, fn (Team $team, User $other): array => ['delete', route('teams.invitations.destroy', [$team, TeamInvitation::factory()->for($team)->create()]), []]],
    ];
}

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
