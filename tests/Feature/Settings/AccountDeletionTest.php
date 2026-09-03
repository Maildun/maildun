<?php

use App\Enums\TeamPermission;
use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\TeamEmailIntegration;
use App\Models\User;

function joinTeamAt(Team $team, User $user, TeamRole $role, int $daysAgo): void
{
    $team->members()->attach($user, ['role' => $role->value]);

    $team->memberships()
        ->where('user_id', $user->id)
        ->update(['created_at' => now()->subDays($daysAgo)]);
}

test('a departing owner hands a shared team to its longest standing admin', function () {
    $owner = User::factory()->create();
    $newcomer = User::factory()->create();
    $veteran = User::factory()->create();
    $team = Team::factory()->create();

    joinTeamAt($team, $owner, TeamRole::Owner, 30);
    joinTeamAt($team, $newcomer, TeamRole::Admin, 2);
    joinTeamAt($team, $veteran, TeamRole::Admin, 20);

    $this->actingAs($owner)
        ->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertRedirect('/');

    $team->refresh();

    expect($team->trashed())->toBeFalse()
        ->and($team->owner()?->is($veteran))->toBeTrue()
        ->and($veteran->fresh()->hasTeamPermission($team, TeamPermission::DeleteTeam))->toBeTrue()
        ->and($newcomer->fresh()->teamRole($team))->toBe(TeamRole::Admin->value);
});

test('a shared team with no admin falls back to its longest standing member', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();

    joinTeamAt($team, $owner, TeamRole::Owner, 30);
    joinTeamAt($team, $member, TeamRole::Member, 10);

    $this->actingAs($owner)
        ->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertRedirect('/');

    expect($member->fresh()->teamRole($team->refresh()))->toBe(TeamRole::Owner->value)
        ->and($member->fresh()->hasTeamPermission($team, TeamPermission::RemoveMember))->toBeTrue();
});

test('a team nobody is left to inherit goes with the account', function () {
    $owner = User::factory()->create();
    $personalTeam = $owner->currentTeam;
    $soloTeam = Team::factory()->create();

    joinTeamAt($soloTeam, $owner, TeamRole::Owner, 5);
    $integration = TeamEmailIntegration::factory()->for($soloTeam)->smtp()->create();

    $this->actingAs($owner)
        ->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertRedirect('/');

    expect(Team::withTrashed()->findOrFail($soloTeam->id)->trashed())->toBeTrue()
        ->and(Team::withTrashed()->findOrFail($personalTeam->id)->trashed())->toBeTrue()
        ->and($integration->fresh())->toBeNull();
});

test('deleting an account leaves no team role assignments behind', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create();
    $ownerId = $owner->id;

    joinTeamAt($team, $owner, TeamRole::Owner, 5);
    joinTeamAt($team, User::factory()->create(), TeamRole::Admin, 1);

    $this->actingAs($owner)
        ->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertRedirect('/');

    $this->assertDatabaseMissing('users', ['id' => $ownerId]);
    $this->assertDatabaseMissing('team_members', ['user_id' => $ownerId]);
    $this->assertDatabaseMissing(config('permission.table_names.model_has_roles'), [
        'model_id' => $ownerId,
        'model_type' => User::class,
    ]);
});
