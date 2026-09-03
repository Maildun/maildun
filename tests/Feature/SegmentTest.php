<?php

use App\Actions\Audiences\ApplySegmentRules;
use App\Actions\Audiences\SyncSegmentSubscribers;
use App\Enums\SegmentMatchType;
use App\Enums\SubscriberSource;
use App\Enums\SubscriberStatus;
use App\Models\Audience;
use App\Models\Segment;
use App\Models\SubscribeForm;
use App\Models\Subscriber;
use App\Models\Tag;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('owners can create valid dynamic segments', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();

    $response = $this->actingAs($user)
        ->post(route('audiences.segments.store', [$team, $audience]), [
            'name' => 'Active example subscribers',
            'description' => 'Subscribed contacts at example.com',
            'match_type' => 'all',
            'rules' => [
                ['field' => 'status', 'operator' => 'equals', 'value' => 'subscribed'],
                ['field' => 'email', 'operator' => 'contains', 'value' => '@example.com'],
            ],
        ]);

    $segment = Segment::query()->where('name', 'Active example subscribers')->firstOrFail();

    $response->assertRedirect(route('audiences.segments.show', [$team, $audience, $segment]));
    expect($segment->match_type)->toBe(SegmentMatchType::All)
        ->and($segment->rules)->toHaveCount(2);
});

test('segment validation rejects incompatible operators and foreign forms', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    $foreignForm = SubscribeForm::factory()->create();

    $this->actingAs($user)
        ->post(route('audiences.segments.store', [$team, $audience]), [
            'name' => 'Invalid',
            'match_type' => 'all',
            'rules' => [
                ['field' => 'status', 'operator' => 'contains', 'value' => 'subscribed'],
                ['field' => 'subscribe_form', 'operator' => 'equals', 'value' => $foreignForm->uuid],
            ],
        ])
        ->assertInvalid(['rules.0.operator', 'rules.1.value']);
});

test('segment rule engine supports all and any matching', function () {
    $audience = Audience::factory()->create();
    Subscriber::factory()->for($audience)->create([
        'email' => 'alpha@example.com',
        'first_name' => 'Alpha',
        'status' => SubscriberStatus::Subscribed,
    ]);
    Subscriber::factory()->for($audience)->unsubscribed()->create([
        'email' => 'beta@example.net',
        'first_name' => 'Beta',
    ]);
    Subscriber::factory()->for($audience)->create([
        'email' => 'gamma@example.net',
        'first_name' => 'Gamma',
    ]);

    $rules = [
        ['field' => 'status', 'operator' => 'equals', 'value' => 'subscribed'],
        ['field' => 'email', 'operator' => 'contains', 'value' => '@example.com'],
    ];
    $apply = app(ApplySegmentRules::class);

    $all = $apply->handle($audience, $audience->subscribers()->getQuery(), $rules, SegmentMatchType::All)->pluck('email')->all();
    $any = $apply->handle($audience, $audience->subscribers()->getQuery(), $rules, SegmentMatchType::Any)->pluck('email')->all();

    expect($all)->toEqualCanonicalizing(['alpha@example.com'])
        ->and($any)->toEqualCanonicalizing(['alpha@example.com', 'gamma@example.net']);
});

test('segment rules support form source negative text and date operators', function () {
    $audience = Audience::factory()->create();
    $subscribeForm = SubscribeForm::factory()->for($audience)->create();
    Subscriber::factory()->for($audience)->create([
        'email' => 'form@example.com',
        'source' => SubscriberSource::Form,
        'subscribe_form_id' => $subscribeForm->id,
        'subscribed_at' => now()->subDays(10),
    ]);
    Subscriber::factory()->for($audience)->create([
        'email' => 'manual@blocked.test',
        'source' => SubscriberSource::Manual,
        'subscribed_at' => now(),
    ]);
    $rules = [
        ['field' => 'subscribe_form', 'operator' => 'equals', 'value' => $subscribeForm->uuid],
        ['field' => 'email', 'operator' => 'does_not_contain', 'value' => 'blocked'],
        ['field' => 'subscribed_at', 'operator' => 'before', 'value' => now()->subDays(5)->toDateString()],
    ];

    $matches = app(ApplySegmentRules::class)
        ->handle($audience, $audience->subscribers()->getQuery(), $rules, SegmentMatchType::All)
        ->pluck('email')
        ->all();

    expect($matches)->toEqualCanonicalizing(['form@example.com']);
});

test('segment page shows the materialized subscriber list', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    $subscribers = Subscriber::factory()->for($audience)->count(2)->create();
    $tag = Tag::factory()->for($team)->create(['name' => 'VIP']);
    $subscribers->first()->tags()->attach($tag);
    $segment = Segment::factory()->for($audience)->create();
    app(SyncSegmentSubscribers::class)->handle($segment);

    $this->actingAs($user)
        ->get(route('audiences.segments.show', [$team, $audience, $segment]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('segments/show')
            ->where('subscribers.total', 2)
            ->has('subscribers.data', 2)
            ->has('subscribers.data.0.avatar')
            // SubscriberHoverCard reads subscriber.tags on this page too.
            ->has('subscribers.data.0.tags')
            ->has('subscribers.data.1.tags'));
});

test('creating a segment immediately syncs matching subscribers', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    Subscriber::factory()->for($audience)->create(['status' => SubscriberStatus::Subscribed]);
    Subscriber::factory()->for($audience)->unsubscribed()->create();

    $this->actingAs($user)
        ->post(route('audiences.segments.store', [$team, $audience]), [
            'name' => 'Subscribed only',
            'match_type' => 'all',
            'rules' => [
                ['field' => 'status', 'operator' => 'equals', 'value' => 'subscribed'],
            ],
        ]);

    $segment = Segment::query()->where('name', 'Subscribed only')->firstOrFail();

    expect($segment->rules_synced_at)->not->toBeNull()
        ->and($segment->subscribers()->count())->toBe(1);
});

test('updating segment rules resyncs matching subscribers', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    $matching = Subscriber::factory()->for($audience)->create(['status' => SubscriberStatus::Subscribed]);
    Subscriber::factory()->for($audience)->unsubscribed()->create();
    $segment = Segment::factory()->for($audience)->create([
        'rules' => [['field' => 'status', 'operator' => 'equals', 'value' => 'unsubscribed']],
    ]);
    app(SyncSegmentSubscribers::class)->handle($segment);

    expect($segment->subscribers()->count())->toBe(1);

    $this->actingAs($user)
        ->patch(route('audiences.segments.update', [$team, $audience, $segment]), [
            'name' => $segment->name,
            'match_type' => 'all',
            'rules' => [['field' => 'status', 'operator' => 'equals', 'value' => 'subscribed']],
        ]);

    $segment->refresh();
    expect($segment->subscribers()->pluck('subscribers.id')->all())->toBe([$matching->id]);
});

test('segments sync command materializes matches for every segment', function () {
    $audience = Audience::factory()->create();
    $matching = Subscriber::factory()->for($audience)->create(['status' => SubscriberStatus::Subscribed]);
    Subscriber::factory()->for($audience)->unsubscribed()->create();
    $segment = Segment::factory()->for($audience)->create([
        'rules' => [['field' => 'status', 'operator' => 'equals', 'value' => 'subscribed']],
    ]);

    $this->artisan('segments:sync')->assertSuccessful();

    $segment->refresh();
    expect($segment->rules_synced_at)->not->toBeNull()
        ->and($segment->subscribers()->pluck('subscribers.id')->all())->toBe([$matching->id]);
});

test('segment rule engine supports every phase one operator', function (string $field, string $operator, string $value, string $expectedEmail) {
    $audience = Audience::factory()->create();
    $form = SubscribeForm::factory()->for($audience)->create();
    $otherForm = SubscribeForm::factory()->for($audience)->create();

    Subscriber::factory()->for($audience)->create([
        'email' => 'alpha@example.com',
        'first_name' => 'Alpha',
        'last_name' => 'Sender',
        'status' => SubscriberStatus::Subscribed,
        'source' => SubscriberSource::Form,
        'subscribe_form_id' => $form->id,
        'subscribed_at' => '2026-01-15 12:00:00',
    ]);
    Subscriber::factory()->for($audience)->create([
        'email' => 'beta@example.net',
        'first_name' => 'Beta',
        'last_name' => 'Reader',
        'status' => SubscriberStatus::Unsubscribed,
        'source' => SubscriberSource::Manual,
        'subscribe_form_id' => $otherForm->id,
        'subscribed_at' => '2026-03-15 12:00:00',
    ]);

    $resolvedValue = match ($value) {
        ':form' => $form->uuid,
        default => $value,
    };

    $matches = app(ApplySegmentRules::class)
        ->handle($audience, $audience->subscribers()->getQuery(), [[
            'field' => $field,
            'operator' => $operator,
            'value' => $resolvedValue,
        ]], SegmentMatchType::All)
        ->pluck('email')
        ->all();

    expect($matches)->toBe([$expectedEmail]);
})->with([
    'text equals' => ['email', 'equals', 'ALPHA@example.com', 'alpha@example.com'],
    'text not equals' => ['first_name', 'not_equals', 'Alpha', 'beta@example.net'],
    'text contains' => ['last_name', 'contains', 'send', 'alpha@example.com'],
    'text does not contain' => ['last_name', 'does_not_contain', 'send', 'beta@example.net'],
    'status equals' => ['status', 'equals', 'subscribed', 'alpha@example.com'],
    'status not equals' => ['status', 'not_equals', 'subscribed', 'beta@example.net'],
    'source equals' => ['source', 'equals', 'form', 'alpha@example.com'],
    'source not equals' => ['source', 'not_equals', 'form', 'beta@example.net'],
    'form equals' => ['subscribe_form', 'equals', ':form', 'alpha@example.com'],
    'form not equals' => ['subscribe_form', 'not_equals', ':form', 'beta@example.net'],
    'date before' => ['subscribed_at', 'before', '2026-02-01', 'alpha@example.com'],
    'date after' => ['subscribed_at', 'after', '2026-02-01', 'beta@example.net'],
]);
