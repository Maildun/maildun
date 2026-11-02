<?php

use App\Enums\AutomationTrigger;
use App\Enums\SubscriberLanguage;
use App\Enums\SubscriberSource;
use App\Events\SubscriberLifecycleOccurred;
use App\Models\Audience;
use App\Models\SubscribeForm;
use App\Models\Subscriber;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;

test('a manually added subscriber records who confirmed consent and starts the subscribed automations', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    Event::fake([SubscriberLifecycleOccurred::class]);

    $this->actingAs($user)
        ->post(route('audiences.subscribers.store', [$team, $audience]), [
            'email' => 'ada@example.com',
            'consent_confirmed' => true,
        ], ['REMOTE_ADDR' => '203.0.113.7'])
        ->assertRedirect()
        ->assertInertiaFlash('toast.type', 'success');

    $subscriber = $audience->subscribers()->sole();

    expect($subscriber->source)->toBe(SubscriberSource::Manual)
        ->and($subscriber->consent_text)->toBe('Marketing consent confirmed by a team member.')
        ->and($subscriber->consent_ip)->toBe('203.0.113.7');

    Event::assertDispatched(SubscriberLifecycleOccurred::class, fn (SubscriberLifecycleOccurred $event): bool => $event->trigger === AutomationTrigger::Subscribed
        && $event->subscriber->is($subscriber));
});

test('the subscriber list can be searched by full name', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    Subscriber::factory()->for($audience)->create(['email' => 'ada@example.com', 'first_name' => 'Ada', 'last_name' => 'Lovelace']);
    Subscriber::factory()->for($audience)->create(['email' => 'grace@example.com', 'first_name' => 'Grace', 'last_name' => 'Hopper']);

    $this->actingAs($user)
        ->get(route('audiences.show', [$team, $audience, 'search' => 'ada lovelace']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('subscribers.data', 1)
            ->where('subscribers.data.0.email', 'ada@example.com'));
});

test('the subscriber list can be filtered to members pending confirmation', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    Subscriber::factory()->for($audience)->create(['email' => 'confirmed@example.com']);
    Subscriber::factory()->for($audience)->pendingConfirmation()->create(['email' => 'pending@example.com']);

    $this->actingAs($user)
        ->get(route('audiences.show', [$team, $audience, 'status' => 'pending']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('subscribers.data', 1)
            ->where('subscribers.data.0.email', 'pending@example.com')
            ->where('subscribers.data.0.pending_confirmation', true)
            ->where('filters.status', 'pending'));
});

test('the subscriber list can be filtered by contact tag', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    $vip = Tag::factory()->for($team)->create(['name' => 'VIP']);
    $tagged = Subscriber::factory()->for($audience)->create(['email' => 'vip@example.com']);
    $tagged->contact->tags()->attach($vip);
    Subscriber::factory()->for($audience)->create(['email' => 'regular@example.com']);

    $this->actingAs($user)
        ->get(route('audiences.show', [$team, $audience, 'tag' => $vip->uuid]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('subscribers.data', 1)
            ->where('subscribers.data.0.email', 'vip@example.com')
            ->where('filters.tag', $vip->uuid));

    $this->actingAs($user)
        ->get(route('audiences.show', [$team, $audience, 'tag' => 'not-a-tag']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('subscribers.data', 2)
            ->where('filters.tag', 'all'));
});

test('the subscriber list pages twenty rows at a time and keeps the filters in its page links', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    Subscriber::factory()->count(21)->for($audience)->create();
    Subscriber::factory()->for($audience)->unsubscribed()->create();

    $this->actingAs($user)
        ->get(route('audiences.show', [$team, $audience, 'status' => 'subscribed']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('subscribers.data', 20)
            ->where('subscribers.total', 21)
            ->where('subscribers.last_page', 2)
            ->where('subscribers.next_page_url', fn (string $url): bool => str_contains($url, 'status=subscribed') && str_contains($url, 'page=2')));

    $this->actingAs($user)
        ->get(route('audiences.show', [$team, $audience, 'status' => 'subscribed', 'page' => 2]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('subscribers.data', 1));
});

test('bulk unsubscribe starts the unsubscribed automations only for members who were subscribed', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    $subscribed = Subscriber::factory()->for($audience)->create();
    $alreadyUnsubscribed = Subscriber::factory()->for($audience)->unsubscribed()->create();
    Event::fake([SubscriberLifecycleOccurred::class]);

    $this->actingAs($user)
        ->patch(route('audiences.subscribers.bulk-unsubscribe', [$team, $audience]), [
            'ids' => [$subscribed->uuid, $alreadyUnsubscribed->uuid],
        ])
        ->assertRedirect();

    Event::assertDispatchedTimes(SubscriberLifecycleOccurred::class, 1);
    Event::assertDispatched(SubscriberLifecycleOccurred::class, fn (SubscriberLifecycleOccurred $event): bool => $event->trigger === AutomationTrigger::Unsubscribed
        && $event->subscriber->is($subscribed));
});

test('bulk subscriber actions reject an empty or oversized selection', function (string $method, string $routeName, array $extra, int $count) {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    $ids = Subscriber::factory()->count($count)->for($audience)->create()->pluck('uuid')->all();

    $this->actingAs($user)
        ->{$method}(route($routeName, [$team, $audience]), ['ids' => $ids, ...$extra])
        ->assertInvalid('ids');

    expect($audience->subscribers()->count())->toBe($count)
        ->and($audience->subscribers()->where('status', 'unsubscribed')->count())->toBe(0);
})->with([
    'unsubscribe nothing' => ['patch', 'audiences.subscribers.bulk-unsubscribe', [], 0],
    'delete nothing' => ['delete', 'audiences.subscribers.bulk-destroy', [], 0],
    'delete more than 100' => ['delete', 'audiences.subscribers.bulk-destroy', [], 101],
    'tag more than 100' => ['patch', 'audiences.subscribers.bulk-tags', ['action' => 'add', 'tags' => ['VIP']], 101],
    'language for more than 100' => ['patch', 'audiences.subscribers.bulk-language', ['language' => SubscriberLanguage::French->value], 101],
]);

test('bulk tag changes reject an unknown action', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    $subscriber = Subscriber::factory()->for($audience)->create();

    $this->actingAs($user)
        ->patch(route('audiences.subscribers.bulk-tags', [$team, $audience]), [
            'ids' => [$subscriber->uuid],
            'action' => 'replace',
            'tags' => ['VIP'],
        ])
        ->assertInvalid('action');

    expect($subscriber->contact->tags()->count())->toBe(0);
});

test('subscriber rows carry the status, source, tags and join date the hover card previews', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    $form = SubscribeForm::factory()->for($audience)->create(['name' => 'Footer form']);
    $vip = Tag::factory()->for($team)->create(['name' => 'VIP', 'color' => '#ff0000']);
    $subscriber = Subscriber::factory()->for($audience)->fromForm($form)->create([
        'email' => 'ada@example.com',
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'language' => SubscriberLanguage::English,
    ]);
    $subscriber->contact->tags()->attach($vip);

    $this->actingAs($user)
        ->get(route('audiences.show', [$team, $audience]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('subscribers.data.0', fn (Assert $row) => $row
                ->where('uuid', $subscriber->uuid)
                ->where('avatar', $subscriber->avatar)
                ->where('email', 'ada@example.com')
                ->where('first_name', 'Ada')
                ->where('last_name', 'Lovelace')
                ->where('status', 'subscribed')
                ->where('source', SubscriberSource::Form->value)
                ->where('language', SubscriberLanguage::English->value)
                ->where('language_name', 'English')
                ->where('source_form', ['uuid' => $form->uuid, 'name' => 'Footer form'])
                ->where('tags', [['uuid' => $vip->uuid, 'name' => 'VIP', 'color' => '#ff0000']])
                ->where('subscribed_at', $subscriber->subscribed_at->toISOString())
                ->where('pending_confirmation', false)));
});
