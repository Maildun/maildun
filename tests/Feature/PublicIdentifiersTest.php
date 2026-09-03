<?php

use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

test('users and teams receive stable public UUIDs', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();

    expect(Str::isUuid($user->uuid))->toBeTrue()
        ->and(Str::isUuid($team->uuid))->toBeTrue()
        ->and($user->fresh()->uuid)->toBe($user->uuid)
        ->and($team->fresh()->uuid)->toBe($team->uuid)
        ->and($user->uuid)->not->toBe($team->uuid);
});

test('public UUIDs are exposed in settings props', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($user, ['role' => 'owner']);

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.uuid', $user->uuid)
        );

    $this->actingAs($user)
        ->get(route('teams.edit', $team))
        ->assertInertia(fn (Assert $page) => $page
            ->where('team.uuid', $team->uuid)
        );

    $this->actingAs($user)
        ->get(route('teams.members.index', $team))
        ->assertInertia(fn (Assert $page) => $page
            ->where('team.uuid', $team->uuid)
            ->where('members.data.0.uuid', $user->uuid)
        );
});

test('team lists include public team identifiers', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('teams.edit', $user->personalTeam()))
        ->assertInertia(fn (Assert $page) => $page
            ->where('teams.0.uuid', $user->personalTeam()->uuid)
        );
});
