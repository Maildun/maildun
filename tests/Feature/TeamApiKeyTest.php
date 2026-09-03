<?php

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\TeamApiKey;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

test('team members can open api key settings and managers can create keys', function () {
    $owner = User::factory()->create();
    $team = $owner->currentTeam;

    $this->actingAs($owner)
        ->get(route('teams.api.index', $team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('teams/api')
            ->where('team.slug', $team->slug)
            ->where('canManage', true)
            ->has('apiKeys', 0)
            ->has('apiBaseUrl')
            ->missing('audiences')
            ->missing('transactionalEmails'));

    $this->actingAs($owner)
        ->post(route('teams.api.store', $team), ['name' => 'Production'])
        ->assertRedirect()
        ->assertInertiaFlash('apiKey.name', 'Production')
        ->assertInertiaFlash('apiKey.token');

    $apiKey = $team->apiKeys()->sole();

    expect($apiKey->name)->toBe('Production')
        ->and($apiKey->token_hash)->toHaveLength(64)
        ->and($apiKey->prefix)->toHaveLength(12)
        ->and(DB::table('team_api_keys')->value('token_hash'))->not->toContain('maildun_live_');
});

test('the plaintext key authenticates while the database stores only its hash', function () {
    $team = Team::factory()->create();
    $issued = TeamApiKey::issue($team, 'Production');

    expect($issued['token'])->toStartWith(TeamApiKey::TOKEN_PREFIX)
        ->and(TeamApiKey::findToken($issued['token'])?->is($issued['key']))->toBeTrue()
        ->and(TeamApiKey::findToken($issued['token'].'x'))->toBeNull();
});

test('members cannot access api key settings', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $apiKey = TeamApiKey::issue($team, 'Production')['key'];

    $this->actingAs($member)
        ->get(route('teams.api.index', $team))
        ->assertForbidden();

    $this->actingAs($member)
        ->post(route('teams.api.store', $team), ['name' => 'Forbidden'])
        ->assertForbidden();

    $this->actingAs($member)
        ->delete(route('teams.api.destroy', [$team, $apiKey]))
        ->assertForbidden();

    $this->assertModelExists($apiKey);
});

test('managers can revoke only keys owned by the selected team', function () {
    $owner = User::factory()->create();
    $team = $owner->currentTeam;
    $apiKey = TeamApiKey::issue($team, 'Production')['key'];
    $otherKey = TeamApiKey::issue(Team::factory()->create(), 'Other')['key'];

    $this->actingAs($owner)
        ->delete(route('teams.api.destroy', [$team, $otherKey]))
        ->assertNotFound();

    $this->actingAs($owner)
        ->delete(route('teams.api.destroy', [$team, $apiKey]))
        ->assertRedirect();

    $this->assertModelMissing($apiKey);
    $this->assertModelExists($otherKey);
});
