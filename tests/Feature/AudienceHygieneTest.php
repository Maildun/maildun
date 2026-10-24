<?php

use App\Enums\EmailAddressHealthReason;
use App\Enums\EmailAddressHealthStatus;
use App\Enums\EmailDeliveryStatus;
use App\Enums\TeamRole;
use App\Models\Audience;
use App\Models\Email;
use App\Models\EmailAddressHealth;
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

/**
 * What the audience hygiene page sends back: when it counted and how many
 * it showed.
 *
 * @return array{counted_at: string, count: int}
 */
function hygieneSnapshot(int $count): array
{
    return ['counted_at' => now()->toISOString(), 'count' => $count];
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

test('workspace list hygiene shows suppressed addresses as undeliverable', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    $subscriber = Subscriber::factory()->for($audience)->unsubscribed()->create([
        'email' => 'bounced@example.com',
    ]);
    EmailAddressHealth::factory()->for($team)->suppressed()->create([
        'email' => $subscriber->email,
        'reason' => EmailAddressHealthReason::PermanentBounce,
        'detail' => 'General',
        'last_event_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('list_hygiene.index', [
            'current_team' => $team,
            'kind' => 'undeliverable',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('audiences.0.undeliverable_count', 1)
            ->where('filters.kind', 'undeliverable')
            ->where('subscribers.total', 1)
            ->where('subscribers.data.0.uuid', $subscriber->uuid)
            ->where('subscribers.data.0.health_reason', 'permanent_bounce')
            ->where('subscribers.data.0.health_detail', 'General')
            ->where('subscribers.data.0.health_event_at', fn (mixed $value): bool => is_string($value)));
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

test('workspace cleanup removes an undeliverable subscriber but keeps the suppression', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    $subscriber = Subscriber::factory()->for($audience)->unsubscribed()->create([
        'email' => 'complained@example.com',
    ]);
    $health = EmailAddressHealth::factory()->for($team)->suppressed()->create([
        'email' => $subscriber->email,
        'reason' => EmailAddressHealthReason::Complaint,
    ]);

    $this->actingAs($user)
        ->delete(route('list_hygiene.undeliverable.destroy', $team), [
            'subscribers' => [$subscriber->uuid],
        ])
        ->assertRedirect();

    $this->assertModelMissing($subscriber);
    $this->assertModelExists($health);
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

test('workspace undeliverable cleanup keeps subscribers whose address is not suppressed', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    $risky = Subscriber::factory()->for($audience)->create(['email' => 'risky@example.com']);
    EmailAddressHealth::factory()->for($team)->create([
        'email' => $risky->email,
        'status' => EmailAddressHealthStatus::Risky,
    ]);
    $suppressedElsewhere = Subscriber::factory()->for($audience)->create(['email' => 'elsewhere@example.com']);
    EmailAddressHealth::factory()->suppressed()->create(['email' => $suppressedElsewhere->email]);

    $this->actingAs($user)
        ->delete(route('list_hygiene.undeliverable.destroy', $team), [
            'subscribers' => [$risky->uuid, $suppressedElsewhere->uuid],
        ])
        ->assertRedirect()
        ->assertInertiaFlash('toast.message', 'Removed 0 undeliverable subscriber(s). The address remains suppressed.');

    $this->assertModelExists($risky);
    $this->assertModelExists($suppressedElsewhere);
});

test('workspace list hygiene counts drop by what each cleanup removed', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();

    $unconfirmed = Subscriber::factory()->for($audience)->count(2)->create(['subscribed_at' => null]);
    $inactive = Subscriber::factory()->for($audience)->count(2)->create()
        ->each(fn (Subscriber $subscriber): EmailDelivery => sentDelivery($subscriber));
    $undeliverable = Subscriber::factory()->for($audience)->unsubscribed()->create(['email' => 'bounced@example.com']);
    EmailAddressHealth::factory()->for($team)->suppressed()->create(['email' => $undeliverable->email]);
    $engaged = Subscriber::factory()->for($audience)->create();
    sentDelivery($engaged, ['opens_count' => 1, 'first_opened_at' => now()]);

    $this->actingAs($user)
        ->get(route('list_hygiene.index', $team))
        ->assertInertia(fn (Assert $page) => $page
            ->where('audiences.0.subscribers_count', 6)
            ->where('audiences.0.unconfirmed_count', 2)
            ->where('audiences.0.inactive_count', 2)
            ->where('audiences.0.undeliverable_count', 1)
            ->where('subscribers.total', 2));

    $this->actingAs($user)
        ->delete(route('list_hygiene.unconfirmed.destroy', $team), [
            'subscribers' => [$unconfirmed[0]->uuid, $engaged->uuid],
        ])
        ->assertInertiaFlash('toast.message', 'Removed 1 unconfirmed subscriber(s).');

    $this->actingAs($user)
        ->delete(route('list_hygiene.inactive.destroy', $team), [
            'subscribers' => $inactive->pluck('uuid')->all(),
        ])
        ->assertInertiaFlash('toast.message', 'Removed 2 inactive subscriber(s).');

    $this->actingAs($user)
        ->delete(route('list_hygiene.undeliverable.destroy', $team), [
            'subscribers' => [$undeliverable->uuid],
        ])
        ->assertInertiaFlash('toast.message', 'Removed 1 undeliverable subscriber(s). The address remains suppressed.');

    $this->actingAs($user)
        ->get(route('list_hygiene.index', $team))
        ->assertInertia(fn (Assert $page) => $page
            ->where('audiences.0.subscribers_count', 2)
            ->where('audiences.0.unconfirmed_count', 1)
            ->where('audiences.0.inactive_count', 0)
            ->where('audiences.0.undeliverable_count', 0)
            ->where('subscribers.total', 1)
            ->where('subscribers.data.0.uuid', $unconfirmed[1]->uuid));

    $this->actingAs($user)
        ->get(route('audiences.settings.hygiene', [$team, $audience]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('counts.unconfirmed', 1)
            ->where('counts.inactive', 0));
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
        ->delete(route('audiences.settings.hygiene.unconfirmed.destroy', [$team, $audience]), hygieneSnapshot(1))
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
        ->delete(route('audiences.settings.hygiene.inactive.destroy', [$team, $audience]), hygieneSnapshot(1))
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
        ->delete(route('audiences.settings.hygiene.unconfirmed.destroy', [$team, $audience]), hygieneSnapshot(1))
        ->assertForbidden();

    $this->actingAs($member)
        ->delete(route('audiences.settings.hygiene.inactive.destroy', [$team, $audience]), hygieneSnapshot(1))
        ->assertForbidden();
});

test('inactive cleanup keeps subscribers whose only sent campaigns did not track opens', function (bool $tracksClicks) {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    $untracked = Subscriber::factory()->for($audience)->create();
    EmailDelivery::factory()
        ->for(Email::factory()->for($team)->create(['track_opens' => false, 'track_clicks' => $tracksClicks]))
        ->for($untracked)
        ->create(['status' => EmailDeliveryStatus::Sent, 'sent_at' => now()]);
    $inactive = Subscriber::factory()->for($audience)->create();
    sentDelivery($inactive);

    $this->actingAs($user)
        ->get(route('audiences.settings.hygiene', [$team, $audience]))
        ->assertInertia(fn (Assert $page) => $page->where('counts.inactive', 1));
    $this->actingAs($user)
        ->get(route('list_hygiene.index', $team))
        ->assertInertia(fn (Assert $page) => $page->where('audiences.0.inactive_count', 1));

    $this->actingAs($user)
        ->delete(route('list_hygiene.inactive.destroy', $team), ['subscribers' => [$untracked->uuid]])
        ->assertRedirect();
    $this->actingAs($user)
        ->delete(route('audiences.settings.hygiene.inactive.destroy', [$team, $audience]), hygieneSnapshot(1))
        ->assertRedirect();

    $this->assertModelExists($untracked);
    $this->assertModelMissing($inactive);
})->with([
    'opens and clicks untracked' => false,
    'only clicks tracked' => true,
]);

test('audience cleanup removes only the members counted when the page loaded', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    $counted = Subscriber::factory()->for($audience)->create(['subscribed_at' => null]);
    $snapshot = hygieneSnapshot(1);

    $this->travel(5)->minutes();
    $joinedLater = Subscriber::factory()->for($audience)->create(['subscribed_at' => null]);

    $this->actingAs($user)
        ->delete(route('audiences.settings.hygiene.unconfirmed.destroy', [$team, $audience]), $snapshot)
        ->assertSessionHasNoErrors();

    $this->assertModelMissing($counted);
    $this->assertModelExists($joinedLater);
});

test('audience cleanup keeps members changed or newly inactive since the count', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    $counted = Subscriber::factory()->for($audience)->create();
    sentDelivery($counted);
    $edited = Subscriber::factory()->for($audience)->create();
    sentDelivery($edited);
    $notYetSent = Subscriber::factory()->for($audience)->create();
    $snapshot = hygieneSnapshot(2);

    $this->travel(5)->minutes();
    $edited->update(['first_name' => 'Renamed']);
    sentDelivery($notYetSent);

    $this->actingAs($user)
        ->delete(route('audiences.settings.hygiene.inactive.destroy', [$team, $audience]), $snapshot)
        ->assertSessionHasNoErrors();

    $this->assertModelMissing($counted);
    $this->assertModelExists($edited);
    $this->assertModelExists($notYetSent);
});

test('audience cleanup refuses when more members match than the user confirmed', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    $unconfirmed = Subscriber::factory()->for($audience)->count(2)->create(['subscribed_at' => null]);

    $this->actingAs($user)
        ->delete(route('audiences.settings.hygiene.unconfirmed.destroy', [$team, $audience]), hygieneSnapshot(1))
        ->assertSessionHasErrors('count');

    $unconfirmed->each(fn (Subscriber $subscriber) => $this->assertModelExists($subscriber));
});

test('audience cleanup requires the confirmed count', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    $unconfirmed = Subscriber::factory()->for($audience)->create(['subscribed_at' => null]);

    $this->actingAs($user)
        ->delete(route('audiences.settings.hygiene.unconfirmed.destroy', [$team, $audience]))
        ->assertSessionHasErrors(['counted_at', 'count']);

    $this->assertModelExists($unconfirmed);
});
