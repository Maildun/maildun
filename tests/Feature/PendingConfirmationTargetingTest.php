<?php

use App\Models\Audience;
use App\Models\Segment;
use App\Models\Subscriber;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * One member in each state: confirmed, waiting on double opt-in, opted out,
 * and opted out but waiting to confirm a rejoin.
 */
function audienceWithEveryMembershipState(User $user): Audience
{
    $audience = Audience::factory()->for($user->currentTeam)->create(['double_opt_in' => true]);
    Subscriber::factory()->for($audience)->create(['email' => 'confirmed@example.com']);
    Subscriber::factory()->for($audience)->pendingConfirmation()->create(['email' => 'unconfirmed@example.com']);
    Subscriber::factory()->for($audience)->unsubscribed()->create(['email' => 'left@example.com']);
    Subscriber::factory()->for($audience)->unsubscribed()->create([
        'email' => 'rejoining@example.com',
        'resubscribe_requested_at' => now(),
    ]);

    return $audience;
}

test('the audience status filter separates pending members from subscribed ones', function (string $status, array $expected) {
    $user = User::factory()->create();
    $audience = audienceWithEveryMembershipState($user);

    $this->actingAs($user)
        ->get(route('audiences.show', [$user->currentTeam, $audience, 'status' => $status]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.status', $status)
            ->where('subscribers.data', fn ($rows): bool => collect($rows)->pluck('email')->sort()->values()->all() === $expected));

    $export = $this->actingAs($user)
        ->get(route('audiences.exports.show', [$user->currentTeam, $audience, 'csv', 'status' => $status]))
        ->streamedContent();

    preg_match_all('/^(\w+@example\.com)/m', $export, $matches);
    expect(collect($matches[1])->sort()->values()->all())->toBe($expected);
})->with([
    'subscribed' => ['subscribed', ['confirmed@example.com']],
    'pending' => ['pending', ['rejoining@example.com', 'unconfirmed@example.com']],
    'unsubscribed' => ['unsubscribed', ['left@example.com', 'rejoining@example.com']],
]);

test('segment status rules can target pending members', function (string $operator, string $value, array $expected) {
    $user = User::factory()->create();
    $audience = audienceWithEveryMembershipState($user);

    $this->actingAs($user)
        ->post(route('audiences.segments.store', [$user->currentTeam, $audience]), [
            'name' => 'By status',
            'match_type' => 'all',
            'rules' => [['field' => 'status', 'operator' => $operator, 'value' => $value]],
        ])
        ->assertSessionHasNoErrors();

    expect(Segment::query()->sole()->subscribers()->orderBy('subscribers.email')->pluck('subscribers.email')->all())->toBe($expected);
})->with([
    'is pending' => ['equals', 'pending', ['rejoining@example.com', 'unconfirmed@example.com']],
    'is subscribed' => ['equals', 'subscribed', ['confirmed@example.com']],
    'is not subscribed' => ['not_equals', 'subscribed', ['left@example.com', 'rejoining@example.com', 'unconfirmed@example.com']],
]);

test('segment status rules reject unknown states', function () {
    $user = User::factory()->create();
    $audience = Audience::factory()->for($user->currentTeam)->create();

    $this->actingAs($user)
        ->post(route('audiences.segments.store', [$user->currentTeam, $audience]), [
            'name' => 'Invalid',
            'match_type' => 'all',
            'rules' => [['field' => 'status', 'operator' => 'equals', 'value' => 'bounced']],
        ])
        ->assertInvalid(['rules.0.value']);
});
