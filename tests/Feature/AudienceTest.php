<?php

use App\Enums\TeamRole;
use App\Models\Audience;
use App\Models\Segment;
use App\Models\SubscribeForm;
use App\Models\Subscriber;
use App\Models\Team;
use App\Models\TeamEmailIntegration;
use App\Models\TeamSender;
use App\Models\TransactionalEmail;
use App\Models\User;
use App\Services\DiceBearAvatarGenerator;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;

test('audiences receive a stable local shape-grid avatar', function () {
    $audience = Audience::factory()->create();

    $expectedAvatar = URL::signedRoute('avatars.show', [
        'style' => DiceBearAvatarGenerator::SHAPE_GRID,
        'seed' => $audience->uuid,
    ], absolute: false);

    expect($audience->avatar)
        ->toBe($expectedAvatar)
        ->not->toContain('api.dicebear.com')
        ->and($audience->fresh()->avatar)->toBe($expectedAvatar)
        ->and($audience->toArray()['avatar'])->toBe($expectedAvatar);
});

test('team owners can manage audiences', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    TeamEmailIntegration::factory()->for($team)->withoutSender()->create();
    TeamSender::factory()->for($team)->create([
        'name' => 'News',
        'email' => 'news@example.com',
        'reply_to' => 'replies@example.com',
    ]);

    $response = $this->actingAs($user)->post(route('audiences.store', $team), [
        'name' => 'Product updates',
        'description' => 'Customers interested in product releases.',
    ]);

    $audience = Audience::query()->where('name', 'Product updates')->firstOrFail();
    $expectedAvatar = URL::signedRoute('avatars.show', [
        'style' => DiceBearAvatarGenerator::SHAPE_GRID,
        'seed' => $audience->uuid,
    ], absolute: false);

    $response->assertRedirect(route('audiences.show', [$team, $audience]));
    $this->assertModelExists($audience);

    $this->actingAs($user)
        ->get(route('audiences.index', $team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('audiences/index')
            ->where('canManage', true)
            ->where('hasAudiences', true)
            ->where('filters.search', '')
            ->where('filters.filter', 'all')
            ->where('filters.sort', 'newest')
            ->has('audiences.data', 1)
            ->where('audiences.data.0.uuid', $audience->uuid)
            ->where(
                'audiences.data.0.avatar',
                $expectedAvatar,
            ));

    $this->actingAs($user)
        ->patch(route('audiences.update', [$team, $audience]), [
            'name' => 'Release updates',
            'description' => null,
            'from_name' => 'News',
            'from_address' => 'news@example.com',
            'reply_to' => 'replies@example.com',
            'notification_email' => 'alerts@example.com',
            'subscribed_url' => 'https://example.com/thanks',
            'already_subscribed_url' => 'https://example.com/already',
            'unsubscribed_url' => 'https://example.com/bye',
        ])
        ->assertRedirect();

    expect($audience->fresh())
        ->name->toBe('Release updates')
        ->from_name->toBe('News')
        ->from_address->toBe('news@example.com')
        ->reply_to->toBe('replies@example.com')
        ->notification_email->toBe('alerts@example.com')
        ->subscribed_url->toBe('https://example.com/thanks')
        ->already_subscribed_url->toBe('https://example.com/already')
        ->unsubscribed_url->toBe('https://example.com/bye');
});

test('team owners can open audience settings', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();

    $this->actingAs($user)
        ->get(route('audiences.edit', [$team, $audience]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('audiences/edit')
            ->where('audience.uuid', $audience->uuid)
            ->where('audience.name', $audience->name)
            ->where('audience.description', $audience->description)
            ->where('audience.avatar', $audience->avatar)
            ->where('audience.from_name', null)
            ->where('audience.notification_email', null)
            ->where('audience.subscribed_url', null)
            ->where('stats.subscribers', 0)
            ->where('stats.subscribed', 0)
            ->where('stats.segments', 0)
            ->where('stats.forms', 0)
            ->missing('tab')
            ->missing('attributes')
            ->missing('senderFallbacks')
            ->has('stats.created_at'));

    $this->actingAs($user)
        ->get(route('audiences.settings.sender', [$team, $audience]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('audiences/settings/sender')
            ->has('senderFallbacks'));

    $this->actingAs($user)
        ->get(route('audiences.settings.notifications', [$team, $audience]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('audiences/settings/notifications'));

    $this->actingAs($user)
        ->get(route('audiences.settings.double-opt-in', [$team, $audience]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('audiences/settings/double-opt-in')
            ->where('audience.double_opt_in', false)
            ->where('audience.double_opt_in_email_uuid', null)
            ->has('transactionalEmails', 0));

    $this->actingAs($user)
        ->get(route('audiences.settings.landing-pages', [$team, $audience]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('audiences/settings/landing-pages'));

    $this->actingAs($user)
        ->get(route('audiences.settings.danger', [$team, $audience]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('audiences/settings/danger'));
});

test('audience settings include related counts', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    Subscriber::factory()->for($audience)->count(2)->create();
    Subscriber::factory()->for($audience)->unsubscribed()->create();
    Segment::factory()->for($audience)->create();
    SubscribeForm::factory()->for($audience)->create();

    $this->actingAs($user)
        ->get(route('audiences.edit', [$team, $audience]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('audiences/edit')
            ->where('stats.subscribers', 3)
            ->where('stats.subscribed', 2)
            ->where('stats.segments', 1)
            ->where('stats.forms', 1));

    $this->actingAs($user)
        ->get(route('audiences.settings.danger', [$team, $audience]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('audiences/settings/danger')
            ->where('audience.subscribers_count', 3)
            ->where('audience.segments_count', 1)
            ->where('audience.forms_count', 1));
});

test('members can view audiences but cannot mutate them', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    assignViewerRole($team, $member);
    $audience = Audience::factory()->for($team)->create();

    $this->actingAs($member)
        ->get(route('audiences.show', [$team, $audience]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('audiences/show')
            ->where('canManage', false));

    $this->actingAs($member)
        ->post(route('audiences.store', $team), ['name' => 'Blocked'])
        ->assertForbidden();

    $this->actingAs($member)
        ->patch(route('audiences.update', [$team, $audience]), ['name' => 'Blocked'])
        ->assertForbidden();

    $this->actingAs($member)
        ->get(route('audiences.edit', [$team, $audience]))
        ->assertForbidden();

    $this->actingAs($member)
        ->get(route('audiences.settings.sender', [$team, $audience]))
        ->assertForbidden();

    $this->actingAs($member)
        ->get(route('audiences.settings.attributes', [$team, $audience]))
        ->assertForbidden();

    $this->actingAs($member)
        ->get(route('audiences.settings.double-opt-in', [$team, $audience]))
        ->assertForbidden();
});

test('team owners can configure double opt-in with a published transactional email', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    $publishedEmail = TransactionalEmail::factory()->for($team)->published()->create();
    $draftEmail = TransactionalEmail::factory()->for($team)->create();

    $this->actingAs($user)
        ->get(route('audiences.settings.double-opt-in', [$team, $audience]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('transactionalEmails', 1)
            ->where('transactionalEmails.0.uuid', $publishedEmail->uuid)
            ->where('transactionalEmails.0.published', true));

    $this->actingAs($user)
        ->patch(route('audiences.update', [$team, $audience]), [
            'double_opt_in' => true,
        ])
        ->assertInvalid('double_opt_in_email_uuid');

    expect($audience->fresh())
        ->double_opt_in->toBeFalse()
        ->double_opt_in_email_id->toBeNull();

    $this->actingAs($user)
        ->patch(route('audiences.update', [$team, $audience]), [
            'double_opt_in' => true,
            'double_opt_in_email_uuid' => $publishedEmail->uuid,
        ])
        ->assertRedirect();

    expect($audience->fresh())
        ->double_opt_in->toBeTrue()
        ->double_opt_in_email_id->toBe($publishedEmail->id);

    $this->actingAs($user)
        ->patch(route('audiences.update', [$team, $audience]), [
            'double_opt_in' => true,
            'double_opt_in_email_uuid' => $draftEmail->uuid,
        ])
        ->assertInvalid('double_opt_in_email_uuid');

    expect($audience->fresh())
        ->double_opt_in->toBeTrue()
        ->double_opt_in_email_id->toBe($publishedEmail->id);

    $this->actingAs($user)
        ->patch(route('audiences.update', [$team, $audience]), [
            'double_opt_in' => false,
            'double_opt_in_email_uuid' => $publishedEmail->uuid,
        ])
        ->assertRedirect();

    expect($audience->fresh())
        ->double_opt_in->toBeFalse()
        ->double_opt_in_email_id->toBeNull();
});

test('empty audience subscriber stats are zeroed for the chart', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();

    $this->actingAs($user)
        ->get(route('audiences.show', [$team, $audience]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('audiences/show')
            ->where('subscriberStats.total.value', 0)
            ->where('subscriberStats.total.change', 0)
            ->where('subscriberStats.subscribed.value', 0)
            ->where('subscriberStats.unsubscribed.value', 0)
            ->where('subscriberStats.new_this_week.value', 0)
            ->where('subscriberStats.subscribe_rate.value', 0)
            ->has('subscriberStats.series', 28)
            ->where('subscriberStats.series.27.total', 0)
            ->where('subscriberStats.series.27.previous_total', 0));
});

test('audience show page returns subscriber stats', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    Subscriber::factory()->for($audience)->count(3)->create();
    Subscriber::factory()->for($audience)->unsubscribed()->create();

    $this->actingAs($user)
        ->get(route('audiences.show', [$team, $audience]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('audiences/show')
            ->where('subscriberStats.total.value', 4)
            ->where('subscriberStats.subscribed.value', 3)
            ->where('subscriberStats.unsubscribed.value', 1)
            ->where('subscriberStats.new_this_week.value', 4)
            ->where('subscriberStats.subscribe_rate.value', 75)
            ->has('subscriberStats.series', 28)
            ->where('subscriberStats.series.0.date', now()->startOfDay()->subDays(27)->toDateString())
            ->where('subscriberStats.series.27.date', now()->toDateString())
            ->where('subscriberStats.series.27.total', 4)
            ->has('subscribers.data.0.avatar'));
});

test('audience subscriber stats compare this week with the previous week', function () {
    $this->travelTo('2026-08-19 12:00:00');

    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();

    Subscriber::factory()->for($audience)->count(2)->create([
        'created_at' => now()->subDays(40),
        'subscribed_at' => now()->subDays(40),
    ]);
    Subscriber::factory()->for($audience)->count(3)->create([
        'created_at' => now()->subDays(14),
        'subscribed_at' => now()->subDays(14),
    ]);
    Subscriber::factory()->for($audience)->unsubscribed()->create([
        'created_at' => now()->subDays(14),
        'subscribed_at' => now()->subDays(14),
        'unsubscribed_at' => now()->subDays(10),
    ]);
    Subscriber::factory()->for($audience)->count(2)->create([
        'created_at' => now()->subDays(2),
        'subscribed_at' => now()->subDays(2),
    ]);

    $this->actingAs($user)
        ->get(route('audiences.show', [$team, $audience]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('audiences/show')
            ->where('subscriberStats.total.value', 8)
            ->where('subscriberStats.total.change', 33.3)
            ->where('subscriberStats.subscribed.value', 7)
            ->where('subscriberStats.subscribed.change', 40)
            ->where('subscriberStats.unsubscribed.value', 1)
            ->where('subscriberStats.unsubscribed.change', 0)
            ->where('subscriberStats.new_this_week.value', 2)
            ->where('subscriberStats.new_this_week.change', null)
            ->where('subscriberStats.subscribe_rate.value', 87.5)
            ->where('subscriberStats.subscribe_rate.change', 5)
            ->where('subscriberStats.series.27.date', '2026-08-19')
            ->where('subscriberStats.series.27.total', 8)
            ->where('subscriberStats.series.27.previous_total', 2));
});

test('audiences are isolated between teams', function () {
    $user = User::factory()->create();
    $otherOwner = User::factory()->create();
    $audience = Audience::factory()->for($otherOwner->currentTeam)->create();

    $this->actingAs($user)
        ->get(route('audiences.show', [$user->currentTeam, $audience]))
        ->assertNotFound();

    $this->actingAs($user)
        ->get(route('audiences.edit', [$user->currentTeam, $audience]))
        ->assertNotFound();
});

test('audience settings reject invalid sender addresses and landing page links', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();

    $this->actingAs($user)
        ->patch(route('audiences.update', [$team, $audience]), [
            'name' => $audience->name,
            'from_address' => 'not-an-address',
            'notification_email' => 'also-bad',
            'subscribed_url' => 'not-a-url',
            'already_subscribed_url' => 'ftp://example.com/nope',
        ])
        ->assertInvalid([
            'from_address',
            'notification_email',
            'subscribed_url',
            'already_subscribed_url',
        ]);
});

test('guests are redirected from audience settings', function () {
    $audience = Audience::factory()->create();

    $this->get(route('audiences.edit', [$audience->team, $audience]))
        ->assertRedirect(route('login'));
});

test('the audience list can be searched filtered and sorted', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $product = Audience::factory()->for($team)->create([
        'name' => 'Product updates',
        'description' => 'Customers interested in product releases.',
        'created_at' => now()->subDay(),
    ]);
    $customers = Audience::factory()->for($team)->create([
        'name' => 'Customers',
        'description' => 'Paying accounts.',
        'created_at' => now(),
    ]);
    $empty = Audience::factory()->for($team)->create([
        'name' => 'Empty list',
        'description' => null,
        'created_at' => now()->subDays(2),
    ]);

    Subscriber::factory()->for($product)->count(2)->create();
    Subscriber::factory()->for($customers)->create();
    Segment::factory()->for($product)->create();
    SubscribeForm::factory()->for($customers)->create();

    $this->actingAs($user)
        ->get(route('audiences.index', ['current_team' => $team, 'search' => 'product']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('audiences/index')
            ->where('filters.search', 'product')
            ->has('audiences.data', 1)
            ->where('audiences.data.0.uuid', $product->uuid)
            ->where('hasAudiences', true));

    $this->actingAs($user)
        ->get(route('audiences.index', ['current_team' => $team, 'filter' => 'active']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('audiences.data', 2)
            ->where('filters.filter', 'active'));

    $this->actingAs($user)
        ->get(route('audiences.index', ['current_team' => $team, 'filter' => 'empty']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('audiences.data', 1)
            ->where('audiences.data.0.uuid', $empty->uuid)
            ->where('filters.filter', 'empty'));

    $this->actingAs($user)
        ->get(route('audiences.index', ['current_team' => $team, 'filter' => 'segments']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('audiences.data', 1)
            ->where('audiences.data.0.uuid', $product->uuid));

    $this->actingAs($user)
        ->get(route('audiences.index', ['current_team' => $team, 'filter' => 'forms']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('audiences.data', 1)
            ->where('audiences.data.0.uuid', $customers->uuid));

    $this->actingAs($user)
        ->get(route('audiences.index', ['current_team' => $team, 'sort' => 'subscribers']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('audiences.data', 3)
            ->where('audiences.data.0.uuid', $product->uuid)
            ->where('filters.sort', 'subscribers'));

    $this->actingAs($user)
        ->get(route('audiences.index', ['current_team' => $team, 'filter' => 'unknown', 'sort' => 'nope']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.filter', 'all')
            ->where('filters.sort', 'newest')
            ->has('audiences.data', 3));
});

test('quick editing an audience keeps sender settings', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create([
        'from_name' => 'News',
        'from_address' => 'news@example.com',
        'notification_email' => 'alerts@example.com',
    ]);

    $this->actingAs($user)
        ->patch(route('audiences.update', [$team, $audience]), [
            'name' => 'Renamed list',
            'description' => 'Updated copy',
        ])
        ->assertRedirect();

    expect($audience->fresh())
        ->name->toBe('Renamed list')
        ->description->toBe('Updated copy')
        ->from_name->toBe('News')
        ->from_address->toBe('news@example.com')
        ->notification_email->toBe('alerts@example.com');
});

test('saving a verified audience sender does not require or clear the audience name', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    TeamEmailIntegration::factory()->for($team)->withoutSender()->create();
    $sender = TeamSender::factory()->for($team)->create([
        'name' => 'News',
        'email' => 'news@example.com',
        'reply_to' => 'replies@example.com',
    ]);
    $audience = Audience::factory()->for($team)->create([
        'name' => 'Product updates',
        'notification_email' => 'alerts@example.com',
    ]);

    $this->actingAs($user)
        ->from(route('audiences.settings.sender', [$team, $audience]))
        ->patch(route('audiences.update', [$team, $audience]), [
            'sender_uuid' => $sender->uuid,
        ])
        ->assertRedirect();

    expect($audience->fresh())
        ->name->toBe('Product updates')
        ->from_name->toBe('News')
        ->from_address->toBe('news@example.com')
        ->reply_to->toBe('replies@example.com')
        ->notification_email->toBe('alerts@example.com');
});

test('sender settings select the verified sender currently used by the audience', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    TeamEmailIntegration::factory()->for($team)->withoutSender()->create();
    $sender = TeamSender::factory()->for($team)->create([
        'name' => 'News',
        'email' => 'news@example.com',
        'reply_to' => 'replies@example.com',
    ]);
    $audience = Audience::factory()->for($team)->create([
        'from_name' => 'News',
        'from_address' => 'news@example.com',
        'reply_to' => 'replies@example.com',
    ]);

    $this->actingAs($user)
        ->get(route('audiences.settings.sender', [$team, $audience]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('selectedSenderUuid', $sender->uuid));
});

test('saving the workspace default clears audience sender overrides', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create([
        'from_name' => 'News',
        'from_address' => 'news@example.com',
        'reply_to' => 'replies@example.com',
    ]);

    $this->actingAs($user)
        ->patch(route('audiences.update', [$team, $audience]), [
            'sender_uuid' => 'workspace-default',
        ])
        ->assertRedirect();

    expect($audience->fresh())
        ->from_name->toBeNull()
        ->from_address->toBeNull()
        ->reply_to->toBeNull();
});

test('audience sender selection rejects unverified and cross-workspace senders', function (string $senderState) {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create([
        'from_address' => 'original@example.com',
    ]);
    $sender = $senderState === 'unverified'
        ? TeamSender::factory()->for($team)->pending()->create()
        : TeamSender::factory()->create();

    $this->actingAs($user)
        ->patch(route('audiences.update', [$team, $audience]), [
            'sender_uuid' => $sender->uuid,
        ])
        ->assertSessionHasErrors([
            'sender_uuid' => 'Select a verified sender from this workspace.',
        ]);

    expect($audience->fresh()->from_address)->toBe('original@example.com');
})->with(['unverified', 'cross workspace']);

test('legacy audience settings tabs redirect to dedicated pages', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();

    $this->actingAs($user)
        ->get(route('audiences.edit', [$team, $audience, 'tab' => 'attributes']))
        ->assertRedirect(route('audiences.settings.attributes', [$team, $audience]));

    $this->actingAs($user)
        ->get(route('audiences.edit', [$team, $audience, 'tab' => 'landing-pages']))
        ->assertRedirect(route('audiences.settings.landing-pages', [$team, $audience]));
});

test('audience deletion requires its exact name and cascades owned data', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create(['name' => 'Customers']);
    $subscriber = Subscriber::factory()->for($audience)->create();
    $segment = Segment::factory()->for($audience)->create();
    $subscribeForm = SubscribeForm::factory()->for($audience)->create();

    $this->actingAs($user)
        ->delete(route('audiences.destroy', [$team, $audience]), ['name' => 'Wrong'])
        ->assertInvalid('name');

    $this->assertModelExists($audience);

    $this->actingAs($user)
        ->delete(route('audiences.destroy', [$team, $audience]), ['name' => 'Customers'])
        ->assertRedirect(route('audiences.index', $team));

    $this->assertModelMissing($audience);
    $this->assertModelMissing($subscriber);
    $this->assertModelMissing($segment);
    $this->assertModelMissing($subscribeForm);
});
