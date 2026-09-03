<?php

use App\Enums\EmailDeliveryStatus;
use App\Enums\TeamRole;
use App\Models\Audience;
use App\Models\Email;
use App\Models\EmailDelivery;
use App\Models\Subscriber;
use App\Models\Team;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function sentDelivery(Subscriber $subscriber, array $attributes = []): EmailDelivery
{
    return EmailDelivery::factory()
        ->for(Email::factory()->for($subscriber->audience->team))
        ->for($subscriber)
        ->create([
            'status' => EmailDeliveryStatus::Sent,
            'sent_at' => now(),
            ...$attributes,
        ]);
}

test('list hygiene shows unconfirmed and inactive subscriber counts', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();

    Subscriber::factory()->for($audience)->create(['subscribed_at' => null]);

    $inactive = Subscriber::factory()->for($audience)->create();
    sentDelivery($inactive);

    $opened = Subscriber::factory()->for($audience)->create();
    sentDelivery($opened, ['opens_count' => 1, 'first_opened_at' => now()]);

    $clicked = Subscriber::factory()->for($audience)->create();
    sentDelivery($clicked, ['clicks_count' => 1, 'first_clicked_at' => now()]);

    Subscriber::factory()->for($audience)->create();

    $unsubscribed = Subscriber::factory()->for($audience)->unsubscribed()->create();
    sentDelivery($unsubscribed);

    $this->actingAs($user)
        ->get(route('audiences.settings.hygiene', [$team, $audience]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('audiences/settings/hygiene')
            ->where('counts.unconfirmed', 1)
            ->where('counts.inactive', 1));
});

test('workspace list hygiene shows matching subscribers from every audience', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $firstAudience = Audience::factory()->for($team)->create(['name' => 'Alpha']);
    $secondAudience = Audience::factory()->for($team)->create(['name' => 'Beta']);

    Subscriber::factory()->for($firstAudience)->create([
        'email' => 'alpha@example.com',
        'subscribed_at' => null,
    ]);
    $firstInactive = Subscriber::factory()->for($firstAudience)->create();
    sentDelivery($firstInactive);

    Subscriber::factory()->for($secondAudience)->count(2)->create(['subscribed_at' => null]);
    $secondEngaged = Subscriber::factory()->for($secondAudience)->create();
    sentDelivery($secondEngaged, ['opens_count' => 1, 'first_opened_at' => now()]);

    $this->actingAs($user)
        ->get(route('list_hygiene.index', $team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('list-hygiene/index')
            ->where('canManage', true)
            ->has('audiences', 2)
            ->where('audiences.0.uuid', $firstAudience->uuid)
            ->where('audiences.0.subscribers_count', 2)
            ->where('audiences.0.unconfirmed_count', 1)
            ->where('audiences.0.inactive_count', 1)
            ->where('audiences.1.uuid', $secondAudience->uuid)
            ->where('audiences.1.subscribers_count', 3)
            ->where('audiences.1.unconfirmed_count', 2)
            ->where('audiences.1.inactive_count', 0)
            ->where('filters.kind', 'unconfirmed')
            ->where('filters.audience', 'all')
            ->where('filters.search', '')
            ->where('subscribers.total', 3)
            ->has('subscribers.data', 3));

    $this->actingAs($user)
        ->get(route('list_hygiene.index', [
            'current_team' => $team,
            'kind' => 'inactive',
            'audience' => 'all',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('list-hygiene/index')
            ->where('filters.kind', 'inactive')
            ->where('subscribers.total', 1)
            ->where('subscribers.data.0.uuid', $firstInactive->uuid)
            ->where('subscribers.data.0.audience.uuid', $firstAudience->uuid)
            ->where('subscribers.data.0.last_sent_at', fn (mixed $value): bool => is_string($value)));
});

test('workspace list hygiene filters subscribers by audience and search', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $firstAudience = Audience::factory()->for($team)->create();
    $secondAudience = Audience::factory()->for($team)->create();

    $matching = Subscriber::factory()->for($firstAudience)->create([
        'email' => 'match@example.com',
        'subscribed_at' => null,
    ]);
    Subscriber::factory()->for($firstAudience)->create([
        'email' => 'other@example.com',
        'subscribed_at' => null,
    ]);
    Subscriber::factory()->for($secondAudience)->create([
        'email' => 'match@example.net',
        'subscribed_at' => null,
    ]);

    $this->actingAs($user)
        ->get(route('list_hygiene.index', [
            'current_team' => $team,
            'audience' => $firstAudience->uuid,
            'search' => 'MATCH@',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.audience', $firstAudience->uuid)
            ->where('filters.search', 'MATCH@')
            ->where('subscribers.total', 1)
            ->where('subscribers.data.0.uuid', $matching->uuid));
});

test('workspace cleanup removes selected unconfirmed subscribers only', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $selectedAudience = Audience::factory()->for($team)->create();
    $unselectedAudience = Audience::factory()->for($team)->create();

    $selected = Subscriber::factory()->for($selectedAudience)->create(['subscribed_at' => null]);
    $unselected = Subscriber::factory()->for($unselectedAudience)->create(['subscribed_at' => null]);

    $this->actingAs($user)
        ->delete(route('list_hygiene.unconfirmed.destroy', $team), [
            'subscribers' => [$selected->uuid],
        ])
        ->assertRedirect();

    $this->assertModelMissing($selected);
    $this->assertModelExists($unselected);
});

test('workspace cleanup removes selected inactive subscribers across audiences', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $firstAudience = Audience::factory()->for($team)->create();
    $secondAudience = Audience::factory()->for($team)->create();

    $firstInactive = Subscriber::factory()->for($firstAudience)->create();
    $firstDelivery = sentDelivery($firstInactive);
    $secondInactive = Subscriber::factory()->for($secondAudience)->create();
    $secondDelivery = sentDelivery($secondInactive);

    $this->actingAs($user)
        ->delete(route('list_hygiene.inactive.destroy', $team), [
            'subscribers' => [$firstInactive->uuid, $secondInactive->uuid],
        ])
        ->assertRedirect();

    $this->assertModelMissing($firstInactive);
    $this->assertModelMissing($secondInactive);
    expect($firstDelivery->fresh()->subscriber_id)->toBeNull()
        ->and($secondDelivery->fresh()->subscriber_id)->toBeNull();
});

test('workspace cleanup rejects subscribers from another team', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $otherTeam = Team::factory()->create();
    $otherAudience = Audience::factory()->for($otherTeam)->create();
    $otherSubscriber = Subscriber::factory()->for($otherAudience)->create(['subscribed_at' => null]);

    $this->actingAs($user)
        ->from(route('list_hygiene.index', $team))
        ->delete(route('list_hygiene.unconfirmed.destroy', $team), [
            'subscribers' => [$otherSubscriber->uuid],
        ])
        ->assertRedirect(route('list_hygiene.index', $team))
        ->assertSessionHasErrors('subscribers.0');

    $this->assertModelExists($otherSubscriber);
});

test('workspace cleanup rechecks that selected subscribers match the hygiene rule', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    $confirmed = Subscriber::factory()->for($audience)->create();

    $this->actingAs($user)
        ->delete(route('list_hygiene.unconfirmed.destroy', $team), [
            'subscribers' => [$confirmed->uuid],
        ])
        ->assertRedirect();

    $this->assertModelExists($confirmed);
});

test('unconfirmed cleanup only removes subscribed records without a confirmation timestamp', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    $otherAudience = Audience::factory()->for($team)->create();

    $unconfirmed = Subscriber::factory()->for($audience)->create(['subscribed_at' => null]);
    $confirmed = Subscriber::factory()->for($audience)->create();
    $unsubscribed = Subscriber::factory()->for($audience)->unsubscribed()->create(['subscribed_at' => null]);
    $other = Subscriber::factory()->for($otherAudience)->create(['subscribed_at' => null]);

    $this->actingAs($user)
        ->delete(route('audiences.settings.hygiene.unconfirmed.destroy', [$team, $audience]))
        ->assertRedirect();

    $this->assertModelMissing($unconfirmed);
    $this->assertModelExists($confirmed);
    $this->assertModelExists($unsubscribed);
    $this->assertModelExists($other);
});

test('inactive cleanup only removes subscribed recipients with sent but unengaged campaigns', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();

    $inactive = Subscriber::factory()->for($audience)->create();
    $inactiveDelivery = sentDelivery($inactive);

    $opened = Subscriber::factory()->for($audience)->create();
    sentDelivery($opened, ['opens_count' => 1, 'last_opened_at' => now()]);

    $neverSent = Subscriber::factory()->for($audience)->create();
    $queued = Subscriber::factory()->for($audience)->create();
    EmailDelivery::factory()
        ->for(Email::factory()->for($team))
        ->for($queued)
        ->create();

    $unconfirmed = Subscriber::factory()->for($audience)->create(['subscribed_at' => null]);
    sentDelivery($unconfirmed);

    $unsubscribed = Subscriber::factory()->for($audience)->unsubscribed()->create();
    sentDelivery($unsubscribed);

    $this->actingAs($user)
        ->delete(route('audiences.settings.hygiene.inactive.destroy', [$team, $audience]))
        ->assertRedirect();

    $this->assertModelMissing($inactive);
    $this->assertModelExists($inactiveDelivery);
    expect($inactiveDelivery->fresh()->subscriber_id)->toBeNull();
    $this->assertModelExists($opened);
    $this->assertModelExists($neverSent);
    $this->assertModelExists($queued);
    $this->assertModelExists($unconfirmed);
    $this->assertModelExists($unsubscribed);
});

test('members can review workspace list hygiene but cannot run cleanup', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    assignViewerRole($team, $member);
    $audience = Audience::factory()->for($team)->create();

    $this->actingAs($member)
        ->get(route('list_hygiene.index', $team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('list-hygiene/index')
            ->where('canManage', false));

    $this->actingAs($member)
        ->delete(route('list_hygiene.unconfirmed.destroy', $team), [
            'subscribers' => [$audience->subscribers()->create([
                'email' => 'member-cleanup@example.com',
                'subscribed_at' => null,
            ])->uuid],
        ])
        ->assertForbidden();

    $this->actingAs($member)
        ->delete(route('list_hygiene.inactive.destroy', $team), [
            'subscribers' => [$audience->subscribers()->firstOrFail()->uuid],
        ])
        ->assertForbidden();

    $this->actingAs($member)
        ->get(route('audiences.settings.hygiene', [$team, $audience]))
        ->assertForbidden();

    $this->actingAs($member)
        ->delete(route('audiences.settings.hygiene.unconfirmed.destroy', [$team, $audience]))
        ->assertForbidden();

    $this->actingAs($member)
        ->delete(route('audiences.settings.hygiene.inactive.destroy', [$team, $audience]))
        ->assertForbidden();
});
