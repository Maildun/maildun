<?php

use App\Models\Audience;
use App\Models\AudienceAttribute;
use App\Models\Segment;
use App\Models\Subscriber;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Creates the segment through the web form and returns the emails it matched.
 *
 * @param  array{operator: string, value: string}  $rule
 * @return list<string>
 */
function segmentEmailsForAttributeRule(User $user, Audience $audience, AudienceAttribute $attribute, array $rule): array
{
    test()->actingAs($user)
        ->post(route('audiences.segments.store', [$audience->team, $audience]), [
            'name' => 'Custom field segment',
            'match_type' => 'all',
            'rules' => [['field' => 'attribute', 'attribute' => $attribute->uuid, ...$rule]],
        ])
        ->assertSessionHasNoErrors();

    return Segment::query()->latest('id')->firstOrFail()->subscribers()
        ->orderBy('subscribers.email')
        ->pluck('subscribers.email')
        ->all();
}

test('segments match text custom fields ignoring case', function (string $operator, string $value, array $expected) {
    $user = User::factory()->create();
    $audience = Audience::factory()->for($user->currentTeam)->create();
    $plan = AudienceAttribute::factory()->for($audience)->create(['key' => 'plan']);
    Subscriber::factory()->for($audience)->create(['email' => 'a@example.com', 'attribute_values' => ['plan' => 'Pro Annual']]);
    Subscriber::factory()->for($audience)->create(['email' => 'b@example.com', 'attribute_values' => ['plan' => 'Basic']]);
    Subscriber::factory()->for($audience)->create(['email' => 'c@example.com']);

    expect(segmentEmailsForAttributeRule($user, $audience, $plan, ['operator' => $operator, 'value' => $value]))->toBe($expected);
})->with([
    'equals' => ['equals', 'pro annual', ['a@example.com']],
    'contains' => ['contains', 'PRO', ['a@example.com']],
    'not equals includes members with no value' => ['not_equals', 'Pro Annual', ['b@example.com', 'c@example.com']],
]);

test('segments compare number custom fields numerically and skip values that are not numbers', function (string $operator, string $value, array $expected) {
    $user = User::factory()->create();
    $audience = Audience::factory()->for($user->currentTeam)->create();
    $seats = AudienceAttribute::factory()->number()->for($audience)->create(['key' => 'seats']);
    Subscriber::factory()->for($audience)->create(['email' => 'a@example.com', 'attribute_values' => ['seats' => 5]]);
    Subscriber::factory()->for($audience)->create(['email' => 'b@example.com', 'attribute_values' => ['seats' => '25']]);
    Subscriber::factory()->for($audience)->create(['email' => 'c@example.com', 'attribute_values' => ['seats' => 'many']]);

    expect(segmentEmailsForAttributeRule($user, $audience, $seats, ['operator' => $operator, 'value' => $value]))->toBe($expected);
})->with([
    'greater than' => ['greater_than', '10', ['b@example.com']],
    'less than' => ['less_than', '10', ['a@example.com']],
    'equals' => ['equals', '25', ['b@example.com']],
]);

test('segments compare date custom fields by calendar day', function (string $operator, array $expected) {
    $user = User::factory()->create();
    $audience = Audience::factory()->for($user->currentTeam)->create();
    $renewal = AudienceAttribute::factory()->date()->for($audience)->create(['key' => 'renewal']);
    Subscriber::factory()->for($audience)->create(['email' => 'a@example.com', 'attribute_values' => ['renewal' => '2026-03-01']]);
    Subscriber::factory()->for($audience)->create(['email' => 'b@example.com', 'attribute_values' => ['renewal' => '2026-09-15']]);

    expect(segmentEmailsForAttributeRule($user, $audience, $renewal, ['operator' => $operator, 'value' => '2026-06-01']))->toBe($expected);
})->with([
    'before' => ['before', ['a@example.com']],
    'after' => ['after', ['b@example.com']],
]);

test('custom field rules need a field of this audience, an operator for its type and a typed value', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    $seats = AudienceAttribute::factory()->number()->for($audience)->create();
    $foreign = AudienceAttribute::factory()->for(Audience::factory()->for($team))->create();

    $this->actingAs($user)
        ->post(route('audiences.segments.store', [$team, $audience]), [
            'name' => 'Invalid',
            'match_type' => 'all',
            'rules' => [
                ['field' => 'attribute', 'attribute' => $foreign->uuid, 'operator' => 'equals', 'value' => 'x'],
                ['field' => 'attribute', 'attribute' => $seats->uuid, 'operator' => 'contains', 'value' => '1'],
                ['field' => 'attribute', 'attribute' => $seats->uuid, 'operator' => 'greater_than', 'value' => 'ten'],
                ['field' => 'email', 'operator' => 'greater_than', 'value' => 'a'],
            ],
        ])
        ->assertInvalid(['rules.0.field', 'rules.1.operator', 'rules.2.value', 'rules.3.operator']);
});

test('a rule on a deleted custom field matches nobody, and everyone when negated', function () {
    $user = User::factory()->create();
    $audience = Audience::factory()->for($user->currentTeam)->create();
    $plan = AudienceAttribute::factory()->for($audience)->create(['key' => 'plan']);
    Subscriber::factory()->for($audience)->create(['email' => 'a@example.com', 'attribute_values' => ['plan' => 'Pro']]);
    $segment = Segment::factory()->for($audience)->create([
        'rules' => [['field' => 'attribute', 'attribute' => $plan->uuid, 'operator' => 'equals', 'value' => 'Pro']],
    ]);
    $negated = Segment::factory()->for($audience)->create([
        'rules' => [['field' => 'attribute', 'attribute' => $plan->uuid, 'operator' => 'not_equals', 'value' => 'Pro']],
    ]);
    $plan->delete();

    $this->artisan('segments:sync')->assertSuccessful();

    expect($segment->subscribers()->count())->toBe(0)
        ->and($negated->subscribers()->count())->toBe(1);
});

test('the segment editor offers the audience custom fields', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    $seats = AudienceAttribute::factory()->number()->for($audience)->create(['name' => 'Seats']);

    $this->actingAs($user)
        ->get(route('audiences.show', [$team, $audience]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('segmentRuleOptions.attributes', [['uuid' => $seats->uuid, 'name' => 'Seats', 'type' => 'number']]));
});
