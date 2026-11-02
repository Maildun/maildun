<?php

use App\Enums\TeamRole;
use App\Models\Audience;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('an audience cannot be created or renamed without a name', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create(['name' => 'Newsletter']);

    $this->actingAs($user)
        ->post(route('audiences.store', $team), ['name' => '', 'description' => 'No name'])
        ->assertInvalid('name');

    $this->actingAs($user)
        ->patch(route('audiences.update', [$team, $audience]), ['name' => ''])
        ->assertInvalid('name');

    expect($team->audiences()->count())->toBe(1)
        ->and($audience->fresh()->name)->toBe('Newsletter');
});

test('view-only members cannot delete an audience', function () {
    $owner = User::factory()->create();
    $team = $owner->currentTeam;
    $member = User::factory()->create();
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    assignViewerRole($team, $member);
    $audience = Audience::factory()->for($team)->create(['name' => 'Newsletter']);

    $this->actingAs($member)
        ->delete(route('audiences.destroy', [$team, $audience]), ['name' => 'Newsletter'])
        ->assertForbidden();

    $this->assertModelExists($audience);
});

test('another workspace cannot edit or delete an audience', function () {
    $user = User::factory()->create();
    $otherOwner = User::factory()->create();
    $audience = Audience::factory()->for($otherOwner->currentTeam)->create(['name' => 'Theirs']);

    $this->actingAs($user)
        ->patch(route('audiences.update', [$user->currentTeam, $audience]), ['name' => 'Mine now'])
        ->assertNotFound();

    $this->actingAs($user)
        ->delete(route('audiences.destroy', [$user->currentTeam, $audience]), ['name' => 'Theirs'])
        ->assertNotFound();

    expect($audience->fresh()->name)->toBe('Theirs');
});

test('the audience list search matches descriptions and can sort oldest first', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $older = Audience::factory()->for($team)->create([
        'name' => 'Customers',
        'description' => 'Paying accounts.',
        'created_at' => now()->subDay(),
    ]);
    $newer = Audience::factory()->for($team)->create([
        'name' => 'Leads',
        'description' => 'Trial signups.',
        'created_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('audiences.index', ['current_team' => $team, 'search' => 'paying']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('audiences.data', 1)
            ->where('audiences.data.0.uuid', $older->uuid));

    $this->actingAs($user)
        ->get(route('audiences.index', ['current_team' => $team, 'sort' => 'oldest']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.sort', 'oldest')
            ->where('audiences.data.0.uuid', $older->uuid)
            ->where('audiences.data.1.uuid', $newer->uuid));
});
