<?php

use App\Enums\TeamRole;
use App\Models\Audience;
use App\Models\Subscriber;
use App\Models\Tag;
use App\Models\Team;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('team owners can create rename and delete tags', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $this->actingAs($user)
        ->post(route('tags.store', $team), ['name' => 'VIP'])
        ->assertRedirect();

    $tag = $team->tags()->firstOrFail();
    expect($tag->name)->toBe('VIP')
        ->and($tag->color)->not->toBeNull();

    $this->actingAs($user)
        ->put(route('tags.update', [$team, $tag]), ['name' => 'VIP Customer'])
        ->assertRedirect();

    expect($tag->fresh()->name)->toBe('VIP Customer');

    $this->actingAs($user)
        ->delete(route('tags.destroy', [$team, $tag]))
        ->assertRedirect();

    $this->assertModelMissing($tag);
});

test('tag names must be unique within a team', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    Tag::factory()->for($team)->create(['name' => 'VIP']);

    $this->actingAs($user)
        ->post(route('tags.store', $team), ['name' => 'VIP'])
        ->assertInvalid('name');
});

test('members can create update and delete tags', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $tag = Tag::factory()->for($team)->create();

    $this->actingAs($member)
        ->post(route('tags.store', $team), ['name' => 'New tag'])
        ->assertRedirect();

    $this->actingAs($member)
        ->put(route('tags.update', [$team, $tag]), ['name' => 'Renamed'])
        ->assertRedirect();

    $this->actingAs($member)
        ->delete(route('tags.destroy', [$team, $tag]))
        ->assertRedirect();

    expect($team->tags()->where('name', 'New tag')->exists())->toBeTrue()
        ->and($tag->fresh())->toBeNull();
});

test('creating a subscriber can tag it and create new tags on the fly', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    Tag::factory()->for($team)->create(['name' => 'Newsletter']);

    $this->actingAs($user)
        ->post(route('audiences.subscribers.store', [$team, $audience]), [
            'email' => 'person@example.com',
            'consent_confirmed' => true,
            'tags' => ['Newsletter', 'VIP', '  VIP  '],
        ])
        ->assertRedirect();

    $subscriber = $audience->subscribers()->firstOrFail();

    expect($team->tags()->count())->toBe(2)
        ->and($subscriber->tags()->pluck('name')->sort()->values()->all())->toBe(['Newsletter', 'VIP']);

    $newTag = $team->tags()->where('name', 'VIP')->firstOrFail();
    expect($newTag->color)->not->toBeNull();
});

test('updating a subscriber can retag and untag it', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    $subscriber = Subscriber::factory()->for($audience)->create();
    $existing = Tag::factory()->for($team)->create(['name' => 'Newsletter']);
    $subscriber->tags()->attach($existing);

    $this->actingAs($user)
        ->patch(route('audiences.subscribers.update', [$team, $audience, $subscriber]), [
            'email' => $subscriber->email,
            'tags' => [],
        ])
        ->assertRedirect();

    expect($subscriber->tags()->count())->toBe(0);
    $this->assertModelExists($existing);
});

test('updating a subscriber with no tags can add a brand new tag', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    $subscriber = Subscriber::factory()->for($audience)->create();

    expect($subscriber->tags()->count())->toBe(0);

    $this->actingAs($user)
        ->patch(route('audiences.subscribers.update', [$team, $audience, $subscriber]), [
            'email' => $subscriber->email,
            'tags' => ['Brand New'],
        ])
        ->assertRedirect();

    expect($subscriber->tags()->pluck('name')->all())->toBe(['Brand New']);
});

test('updating a subscriber can add a second tag alongside an existing one', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    $subscriber = Subscriber::factory()->for($audience)->create();
    $existing = Tag::factory()->for($team)->create(['name' => 'First']);
    $subscriber->tags()->attach($existing);

    $this->actingAs($user)
        ->patch(route('audiences.subscribers.update', [$team, $audience, $subscriber]), [
            'email' => $subscriber->email,
            'tags' => ['First', 'Second'],
        ])
        ->assertRedirect();

    expect($subscriber->tags()->pluck('name')->sort()->values()->all())->toBe(['First', 'Second']);
});

test('members can tag subscribers', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $audience = Audience::factory()->for($team)->create();
    $subscriber = Subscriber::factory()->for($audience)->create();

    $this->actingAs($member)
        ->patch(route('audiences.subscribers.update', [$team, $audience, $subscriber]), [
            'email' => $subscriber->email,
            'tags' => ['VIP'],
        ])
        ->assertRedirect();

    expect($subscriber->fresh()->tags()->pluck('name')->all())->toBe(['VIP']);
});

test('deleting a tag removes it from tagged subscribers', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    $subscriber = Subscriber::factory()->for($audience)->create();
    $tag = Tag::factory()->for($team)->create();
    $subscriber->tags()->attach($tag);

    $this->actingAs($user)
        ->delete(route('tags.destroy', [$team, $tag]))
        ->assertRedirect();

    expect($subscriber->tags()->count())->toBe(0);
});

test('the tag settings page lists the team tags with their subscriber counts', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    $subscriber = Subscriber::factory()->for($audience)->create();
    $tag = Tag::factory()->for($team)->create(['name' => 'VIP', 'color' => '#22c55e']);
    $subscriber->tags()->attach($tag);
    Tag::factory()->for(Team::factory()->create())->create(['name' => 'Other team tag']);

    $this->actingAs($user)
        ->get(route('tags.index', $team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('teams/tags')
            ->where('team.slug', $team->slug)
            ->where('permissions.canManageTags', true)
            ->where('colors', Tag::COLORS)
            ->has('tags', 1)
            ->where('tags.0.name', 'VIP')
            ->where('tags.0.color', '#22c55e')
            ->where('tags.0.subscribers_count', 1));
});

test('members can manage tags from the tag settings page', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    Tag::factory()->for($team)->create();

    $this->actingAs($member)
        ->get(route('tags.index', $team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('teams/tags')
            ->where('permissions.canManageTags', true)
            ->has('tags', 1));
});

test('non members cannot open the tag settings page', function () {
    $team = Team::factory()->create();
    $team->members()->attach(User::factory()->create(), ['role' => TeamRole::Owner->value]);

    $this->actingAs(User::factory()->create())
        ->get(route('tags.index', $team))
        ->assertForbidden();
});

test('guests are redirected from the tag settings page', function () {
    $team = Team::factory()->create();

    $this->get(route('tags.index', $team))->assertRedirect(route('login'));
});

test('tags can be created and recolored with a palette color', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $this->actingAs($user)
        ->post(route('tags.store', $team), ['name' => 'VIP', 'color' => '#0ea5e9'])
        ->assertRedirect();

    $tag = $team->tags()->firstOrFail();
    expect($tag->color)->toBe('#0ea5e9');

    $this->actingAs($user)
        ->patch(route('tags.update', [$team, $tag]), ['name' => 'VIP', 'color' => '#a855f7'])
        ->assertRedirect();

    expect($tag->fresh()->color)->toBe('#a855f7');
});

test('tag colors must be hex values', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $this->actingAs($user)
        ->post(route('tags.store', $team), ['name' => 'VIP', 'color' => 'blue'])
        ->assertInvalid('color');
});

test('owners can bulk delete selected tags', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $keep = Tag::factory()->for($team)->create();
    $first = Tag::factory()->for($team)->create();
    $second = Tag::factory()->for($team)->create();
    $audience = Audience::factory()->for($team)->create();
    $subscriber = Subscriber::factory()->for($audience)->create();
    $subscriber->tags()->attach($first);

    $this->actingAs($user)
        ->delete(route('tags.bulk-destroy', $team), [
            'ids' => [$first->uuid, $second->uuid],
        ])
        ->assertRedirect();

    $this->assertModelMissing($first);
    $this->assertModelMissing($second);
    $this->assertModelExists($keep);
    expect($subscriber->tags()->count())->toBe(0);
});

test('bulk tag deletion stays scoped to the team for members and owners', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $tag = Tag::factory()->for($team)->create();
    $foreign = Tag::factory()->for(Team::factory()->create())->create();

    $this->actingAs($member)
        ->delete(route('tags.bulk-destroy', $team), [
            'ids' => [$tag->uuid],
        ])
        ->assertRedirect();

    $this->actingAs($owner)
        ->delete(route('tags.bulk-destroy', $team), [
            'ids' => [$foreign->uuid],
        ])
        ->assertInvalid('ids.0');

    $this->assertModelMissing($tag);
    $this->assertModelExists($foreign);
});

test('tags cannot be managed through another team', function () {
    $user = User::factory()->create();
    $otherTeam = Team::factory()->create();
    $otherTeam->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $tag = Tag::factory()->for($user->currentTeam)->create();

    $this->actingAs($user)
        ->delete(route('tags.destroy', [$otherTeam, $tag]))
        ->assertNotFound();

    $this->assertModelExists($tag);
});
