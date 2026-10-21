<?php

use App\Actions\Audiences\ApplySegmentRules;
use App\Actions\Audiences\SyncSegmentSubscribers;
use App\Enums\EmailStatus;
use App\Enums\SegmentMatchType;
use App\Enums\SubscriberLanguage;
use App\Enums\SubscriberSource;
use App\Enums\SubscriberStatus;
use App\Models\Audience;
use App\Models\Email;
use App\Models\Segment;
use App\Models\SubscribeForm;
use App\Models\Subscriber;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Str;
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

test('owners can delete a segment no active campaign targets', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    $segment = Segment::factory()->for($audience)->create();
    Email::factory()->for($team)->create([
        'audience_id' => $audience->id,
        'segment_id' => $segment->id,
        'status' => EmailStatus::Sent,
    ]);

    $this->actingAs($user)
        ->delete(route('audiences.segments.destroy', [$team, $audience, $segment]))
        ->assertRedirect(route('audiences.show', [$team, $audience]));

    expect(Segment::query()->whereKey($segment->id)->exists())->toBeFalse();
});

test('segments targeted by an unsent campaign cannot be deleted', function (array $campaignAttributes) {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    $segment = Segment::factory()->for($audience)->create();
    Email::factory()->for($team)->create([
        'name' => 'Launch',
        'audience_id' => $audience->id,
        'segment_id' => $segment->id,
        ...$campaignAttributes,
    ]);

    $this->actingAs($user)
        ->from(route('audiences.segments.show', [$team, $audience, $segment]))
        ->delete(route('audiences.segments.destroy', [$team, $audience, $segment]))
        ->assertRedirect(route('audiences.segments.show', [$team, $audience, $segment]))
        ->assertSessionHasErrors(['segment' => 'This segment is used by campaigns that have not been sent yet: Launch.']);

    expect(Segment::query()->whereKey($segment->id)->exists())->toBeTrue();
})->with([
    'sending' => [['status' => EmailStatus::Sending]],
    'scheduled' => [['status' => EmailStatus::Draft, 'scheduled_at' => now()->addDay()]],
    'unscheduled draft' => [['status' => EmailStatus::Draft, 'scheduled_at' => null]],
]);

test('segment rules cannot change while a campaign to it is in flight', function (EmailStatus $status) {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    $rules = [['field' => 'language', 'operator' => 'equals', 'value' => 'fr']];
    $segment = Segment::factory()->for($audience)->create(['name' => 'French', 'rules' => $rules]);
    Email::factory()->for($team)->create([
        'name' => 'Launch',
        'audience_id' => $audience->id,
        'segment_id' => $segment->id,
        'status' => $status,
    ]);

    $this->actingAs($user)
        ->from(route('audiences.segments.show', [$team, $audience, $segment]))
        ->patch(route('audiences.segments.update', [$team, $audience, $segment]), [
            'name' => 'French',
            'match_type' => 'any',
            'rules' => [['field' => 'status', 'operator' => 'equals', 'value' => 'subscribed']],
        ])
        ->assertSessionHasErrors(['rules' => 'The rules cannot change while these campaigns are sending: Launch.']);

    $segment->refresh();
    expect($segment->rules)->toBe($rules)
        ->and($segment->match_type)->toBe(SegmentMatchType::All);
})->with([EmailStatus::Queued, EmailStatus::Sending]);

test('a sending segment can still be renamed when its rules stay the same', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    $rules = [['field' => 'language', 'operator' => 'equals', 'value' => 'fr']];
    $segment = Segment::factory()->for($audience)->create(['name' => 'French', 'rules' => $rules]);
    Email::factory()->for($team)->create([
        'audience_id' => $audience->id,
        'segment_id' => $segment->id,
        'status' => EmailStatus::Sending,
    ]);

    $this->actingAs($user)
        ->patch(route('audiences.segments.update', [$team, $audience, $segment]), [
            'name' => 'French readers',
            'description' => 'Readers who chose French',
            'match_type' => 'all',
            'rules' => $rules,
        ])
        ->assertSessionHasNoErrors();

    expect($segment->refresh()->only(['name', 'description', 'rules']))->toBe([
        'name' => 'French readers',
        'description' => 'Readers who chose French',
        'rules' => $rules,
    ]);
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

test('narrowing the rules of a segment with more members than one query can bind removes every non-match', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    $now = now()->toDateTimeString();
    foreach (array_chunk(range(1, 33000), 500) as $chunk) {
        DB::table('subscribers')->insert(array_map(fn (int $number): array => [
            'uuid' => (string) Str::uuid(),
            'audience_id' => $audience->id,
            'email' => "user{$number}@example.com",
            'status' => SubscriberStatus::Subscribed->value,
            'source' => SubscriberSource::Manual->value,
            'subscribed_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ], $chunk));
    }
    $segment = Segment::factory()->for($audience)->create([
        'rules' => [['field' => 'email', 'operator' => 'contains', 'value' => '@example.com']],
    ]);
    app(SyncSegmentSubscribers::class)->handle($segment);

    expect($segment->subscribers()->count())->toBe(33000);

    $this->actingAs($user)
        ->patch(route('audiences.segments.update', [$team, $audience, $segment]), [
            'name' => $segment->name,
            'match_type' => 'all',
            'rules' => [['field' => 'email', 'operator' => 'equals', 'value' => 'user1@example.com']],
        ])
        ->assertRedirect();

    expect($segment->subscribers()->pluck('subscribers.email')->all())->toBe(['user1@example.com']);
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

test('segments sync command keeps syncing after one segment fails', function () {
    Exceptions::fake();
    $audience = Audience::factory()->create();
    $matching = Subscriber::factory()->for($audience)->create(['status' => SubscriberStatus::Subscribed]);
    $broken = Segment::factory()->for($audience)->create([
        'rules' => [['field' => 'retired_field', 'operator' => 'equals', 'value' => 'x']],
    ]);
    $healthy = Segment::factory()->for($audience)->create([
        'rules' => [['field' => 'status', 'operator' => 'equals', 'value' => 'subscribed']],
    ]);

    $this->artisan('segments:sync')->assertFailed();

    Exceptions::assertReported(ValueError::class);
    expect($broken->refresh()->rules_synced_at)->toBeNull()
        ->and($healthy->refresh()->rules_synced_at)->not->toBeNull()
        ->and($healthy->subscribers()->pluck('subscribers.id')->all())->toBe([$matching->id]);
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
        'language' => SubscriberLanguage::English,
    ]);
    Subscriber::factory()->for($audience)->create([
        'email' => 'beta@example.net',
        'first_name' => 'Beta',
        'last_name' => 'Reader',
        'status' => SubscriberStatus::Unsubscribed,
        'source' => SubscriberSource::Manual,
        'subscribe_form_id' => $otherForm->id,
        'subscribed_at' => '2026-03-15 12:00:00',
        'language' => SubscriberLanguage::Indonesian,
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
    'language equals' => ['language', 'equals', 'en', 'alpha@example.com'],
    'language not equals' => ['language', 'not_equals', 'en', 'beta@example.net'],
]);

test('language not equals also matches subscribers without a language', function () {
    $audience = Audience::factory()->create();
    Subscriber::factory()->for($audience)->create(['email' => 'english@example.com', 'language' => SubscriberLanguage::English]);
    Subscriber::factory()->for($audience)->create(['email' => 'unknown@example.com', 'language' => null]);

    $matches = app(ApplySegmentRules::class)
        ->handle($audience, $audience->subscribers()->getQuery(), [
            ['field' => 'language', 'operator' => 'not_equals', 'value' => 'en'],
        ], SegmentMatchType::All)
        ->pluck('email')
        ->all();

    expect($matches)->toBe(['unknown@example.com']);
});

test('negative rules also match subscribers whose field is empty', function (string $field, string $operator, string $value) {
    $audience = Audience::factory()->create();
    $form = SubscribeForm::factory()->for($audience)->create();
    Subscriber::factory()->for($audience)->create([
        'email' => 'filled@example.com',
        'first_name' => 'Test',
        'last_name' => 'Tester',
        'subscribe_form_id' => $form->id,
    ]);
    Subscriber::factory()->for($audience)->create([
        'email' => 'empty@example.com',
        'first_name' => null,
        'last_name' => null,
        'subscribe_form_id' => null,
    ]);

    $matches = app(ApplySegmentRules::class)
        ->handle($audience, $audience->subscribers()->getQuery(), [[
            'field' => $field,
            'operator' => $operator,
            'value' => $value === ':form' ? $form->uuid : $value,
        ]], SegmentMatchType::All)
        ->pluck('email')
        ->all();

    expect($matches)->toBe(['empty@example.com']);
})->with([
    'first name not equals' => ['first_name', 'not_equals', 'Test'],
    'last name does not contain' => ['last_name', 'does_not_contain', 'test'],
    'subscribe form not equals' => ['subscribe_form', 'not_equals', ':form'],
]);

test('text rules match wildcard characters literally', function (string $operator, string $value, string $expectedEmail) {
    $audience = Audience::factory()->create();
    Subscriber::factory()->for($audience)->create(['email' => 'snake_case!50%@example.com']);
    Subscriber::factory()->for($audience)->create(['email' => 'plain@example.com']);

    $matches = app(ApplySegmentRules::class)
        ->handle($audience, $audience->subscribers()->getQuery(), [[
            'field' => 'email',
            'operator' => $operator,
            'value' => $value,
        ]], SegmentMatchType::All)
        ->pluck('email')
        ->all();

    expect($matches)->toBe([$expectedEmail]);
})->with([
    'contains an underscore' => ['contains', '_', 'snake_case!50%@example.com'],
    'contains a percent sign' => ['contains', '%', 'snake_case!50%@example.com'],
    'contains the escape character' => ['contains', '!', 'snake_case!50%@example.com'],
    'does not contain an underscore' => ['does_not_contain', '_', 'plain@example.com'],
]);

test('segment date rules accept only YYYY-MM-DD dates', function (string $value) {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();

    $this->actingAs($user)
        ->post(route('audiences.segments.store', [$team, $audience]), [
            'name' => 'Recent',
            'match_type' => 'all',
            'rules' => [['field' => 'subscribed_at', 'operator' => 'after', 'value' => $value]],
        ])
        ->assertInvalid(['rules.0.value' => 'The date must be in YYYY-MM-DD format.']);

    expect($audience->segments()->exists())->toBeFalse();
})->with(['1 month ago', '04/20/2026', '2026-4-20', '2026-02-30', '2026-04-20 10:00']);

test('a stored date rule in an older format still matches by its calendar day', function () {
    $audience = Audience::factory()->create();
    Subscriber::factory()->for($audience)->create(['email' => 'old@example.com', 'subscribed_at' => '2026-01-15 12:00:00']);
    Subscriber::factory()->for($audience)->create(['email' => 'new@example.com', 'subscribed_at' => '2026-05-15 12:00:00']);

    $matches = app(ApplySegmentRules::class)
        ->handle($audience, $audience->subscribers()->getQuery(), [
            ['field' => 'subscribed_at', 'operator' => 'before', 'value' => '04/20/2026'],
        ], SegmentMatchType::All)
        ->pluck('email')
        ->all();

    expect($matches)->toBe(['old@example.com']);
});

test('segment validation rejects unknown languages and text operators on language', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();

    $this->actingAs($user)
        ->post(route('audiences.segments.store', [$team, $audience]), [
            'name' => 'Invalid language',
            'match_type' => 'all',
            'rules' => [
                ['field' => 'language', 'operator' => 'equals', 'value' => 'xx'],
                ['field' => 'language', 'operator' => 'contains', 'value' => 'en'],
            ],
        ])
        ->assertInvalid(['rules.0.value', 'rules.1.operator']);
});

test('negative name rules also match subscribers without a name', function (string $operator) {
    $audience = Audience::factory()->create();
    Subscriber::factory()->for($audience)->create(['email' => 'test@example.com', 'first_name' => 'Test']);
    Subscriber::factory()->for($audience)->create(['email' => 'noname@example.com', 'first_name' => null]);

    $matches = app(ApplySegmentRules::class)
        ->handle($audience, $audience->subscribers()->getQuery(), [
            ['field' => 'first_name', 'operator' => $operator, 'value' => 'test'],
        ], SegmentMatchType::All)
        ->pluck('email')
        ->all();

    expect($matches)->toBe(['noname@example.com']);
})->with(['not_equals', 'does_not_contain']);

test('subscribe form not equals also matches subscribers without a form', function () {
    $audience = Audience::factory()->create();
    $formA = SubscribeForm::factory()->for($audience)->create();
    $formB = SubscribeForm::factory()->for($audience)->create();
    Subscriber::factory()->for($audience)->create(['email' => 'form-a@example.com', 'subscribe_form_id' => $formA->id]);
    Subscriber::factory()->for($audience)->create(['email' => 'form-b@example.com', 'subscribe_form_id' => $formB->id]);
    Subscriber::factory()->for($audience)->create(['email' => 'manual@example.com', 'subscribe_form_id' => null]);

    $matches = app(ApplySegmentRules::class)
        ->handle($audience, $audience->subscribers()->getQuery(), [
            ['field' => 'subscribe_form', 'operator' => 'not_equals', 'value' => $formA->uuid],
        ], SegmentMatchType::All)
        ->orderBy('email')
        ->pluck('email')
        ->all();

    expect($matches)->toBe(['form-b@example.com', 'manual@example.com']);
});
