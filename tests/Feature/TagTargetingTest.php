<?php

use App\Models\Audience;
use App\Models\Contact;
use App\Models\Segment;
use App\Models\Subscriber;
use App\Models\Tag;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('contacts and their export can be filtered by tag', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $vip = Tag::factory()->for($team)->create();
    $tagged = Contact::factory()->for($team)->create(['email' => 'tagged@example.com']);
    $tagged->tags()->attach($vip);
    Contact::factory()->for($team)->create(['email' => 'untagged@example.com']);

    $this->actingAs($user)
        ->get(route('contacts.index', ['current_team' => $team, 'tag' => $vip->uuid]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.tag', $vip->uuid)
            ->has('contacts.data', 1)
            ->where('contacts.data.0.uuid', $tagged->uuid));

    expect($this->actingAs($user)
        ->get(route('contacts.exports.show', ['current_team' => $team, 'format' => 'csv', 'tag' => $vip->uuid]))
        ->streamedContent())
        ->toContain('tagged@example.com')
        ->not->toContain('untagged@example.com');
});

test('audience subscribers and their export can be filtered by tag', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    $vip = Tag::factory()->for($team)->create();
    $tagged = Subscriber::factory()->for($audience)->create(['email' => 'tagged@example.com']);
    $tagged->contact->tags()->attach($vip);
    Subscriber::factory()->for($audience)->create(['email' => 'untagged@example.com']);

    $this->actingAs($user)
        ->get(route('audiences.show', [$team, $audience, 'tag' => $vip->uuid]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.tag', $vip->uuid)
            ->has('subscribers.data', 1)
            ->where('subscribers.data.0.uuid', $tagged->uuid));

    expect($this->actingAs($user)
        ->get(route('audiences.exports.show', [$team, $audience, 'csv', 'tag' => $vip->uuid]))
        ->streamedContent())
        ->toContain('tagged@example.com')
        ->not->toContain('untagged@example.com');
});

test('a tag filter that is not a uuid shows everyone', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    Contact::factory()->for($team)->count(2)->create();

    $this->actingAs($user)
        ->get(route('contacts.index', ['current_team' => $team, 'tag' => 'not-a-uuid']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.tag', '')
            ->has('contacts.data', 2));
});

test('segments can target members who have or lack a tag', function (string $operator, string $expectedEmail) {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    $vip = Tag::factory()->for($team)->create();
    Subscriber::factory()->for($audience)->create(['email' => 'tagged@example.com'])->contact->tags()->attach($vip);
    Subscriber::factory()->for($audience)->create(['email' => 'untagged@example.com']);

    $this->actingAs($user)
        ->post(route('audiences.segments.store', [$team, $audience]), [
            'name' => 'VIPs',
            'match_type' => 'all',
            'rules' => [['field' => 'tag', 'operator' => $operator, 'value' => strtoupper($vip->uuid)]],
        ])
        ->assertSessionHasNoErrors();

    expect(Segment::query()->sole()->subscribers()->pluck('subscribers.email')->all())->toBe([$expectedEmail]);
})->with([
    'has the tag' => ['equals', 'tagged@example.com'],
    'lacks the tag' => ['not_equals', 'untagged@example.com'],
]);

test('segment tag rules reject tags from another workspace', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    $foreignTag = Tag::factory()->create();

    $this->actingAs($user)
        ->post(route('audiences.segments.store', [$team, $audience]), [
            'name' => 'Foreign',
            'match_type' => 'all',
            'rules' => [
                ['field' => 'tag', 'operator' => 'equals', 'value' => $foreignTag->uuid],
                ['field' => 'tag', 'operator' => 'contains', 'value' => $foreignTag->uuid],
            ],
        ])
        ->assertInvalid(['rules.0.value', 'rules.1.operator']);
});

test('the segment editor offers the workspace tags', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    $vip = Tag::factory()->for($team)->create(['name' => 'VIP']);
    Tag::factory()->create();

    $this->actingAs($user)
        ->get(route('audiences.show', [$team, $audience]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('segmentRuleOptions.tags', 1)
            ->where('segmentRuleOptions.tags.0', ['uuid' => $vip->uuid, 'name' => 'VIP']));
});
