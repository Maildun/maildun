<?php

use App\Enums\AudienceAttributeType;
use App\Enums\AutomationTrigger;
use App\Enums\SubscriberLanguage;
use App\Enums\SubscriberSource;
use App\Enums\SubscriberStatus;
use App\Events\SubscriberLifecycleOccurred;
use App\Jobs\SendTransactionalEmailDelivery;
use App\Models\Audience;
use App\Models\Automation;
use App\Models\Subscriber;
use App\Models\TeamApiKey;
use App\Models\TeamEmailIntegration;
use App\Models\TransactionalEmail;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

test('the api subscribes idempotently without replacing the shared contact profile', function () {
    $audience = Audience::factory()->create([
        'allowed_languages' => [
            SubscriberLanguage::English->value,
            SubscriberLanguage::Indonesian->value,
        ],
        'default_language' => SubscriberLanguage::English,
    ]);
    $issued = TeamApiKey::issue($audience->team, 'Production');
    Event::fake([SubscriberLifecycleOccurred::class]);

    $this->withToken($issued['token'])
        ->postJson(route('api.v1.audiences.subscribers.store', $audience->uuid), [
            'email' => 'ADA@EXAMPLE.COM',
            'first_name' => 'Ada',
            'language' => SubscriberLanguage::English->value,
            'consent_text' => 'Signed up during checkout',
        ])
        ->assertOk()
        ->assertJsonPath('data.email', 'ada@example.com')
        ->assertJsonPath('data.language', 'en')
        ->assertJsonPath('data.status', 'subscribed');

    $subscriber = $audience->subscribers()->sole();

    expect($subscriber->source)->toBe(SubscriberSource::Api)
        ->and($subscriber->language)->toBe(SubscriberLanguage::English)
        ->and($subscriber->consent_text)->toBe('Signed up during checkout')
        ->and($subscriber->status)->toBe(SubscriberStatus::Subscribed);

    Event::assertDispatched(
        SubscriberLifecycleOccurred::class,
        fn (SubscriberLifecycleOccurred $event): bool => $event->trigger === AutomationTrigger::Subscribed,
    );

    $this->withToken($issued['token'])
        ->postJson(route('api.v1.audiences.subscribers.store', $audience->uuid), [
            'email' => 'ada@example.com',
            'first_name' => 'Augusta Ada',
            'language' => SubscriberLanguage::Indonesian->value,
        ])
        ->assertOk()
        ->assertJsonPath('data.first_name', 'Ada')
        ->assertJsonPath('data.language', 'id');

    expect($audience->subscribers()->count())->toBe(1)
        ->and($subscriber->fresh()->language)->toBe(SubscriberLanguage::Indonesian)
        ->and($audience->team->contacts()->sole()->first_name)->toBe('Ada');
    Event::assertDispatchedTimes(SubscriberLifecycleOccurred::class, 1);
});

test('the api records the consenting person ip when the caller passes it', function () {
    $audience = Audience::factory()->create();
    $issued = TeamApiKey::issue($audience->team, 'Production');

    $this->withToken($issued['token'])
        ->withServerVariables(['REMOTE_ADDR' => '198.51.100.7'])
        ->postJson(route('api.v1.audiences.subscribers.store', $audience->uuid), [
            'email' => 'ada@example.com',
            'consent_ip' => '203.0.113.24',
        ])
        ->assertOk();
    $this->withToken($issued['token'])
        ->withServerVariables(['REMOTE_ADDR' => '198.51.100.7'])
        ->postJson(route('api.v1.audiences.subscribers.store', $audience->uuid), [
            'email' => 'grace@example.com',
        ])
        ->assertOk();

    expect($audience->subscribers()->where('email', 'ada@example.com')->value('consent_ip'))->toBe('203.0.113.24')
        ->and($audience->subscribers()->where('email', 'grace@example.com')->value('consent_ip'))->toBe('198.51.100.7');
});

test('the api rejects a consent ip that is not an ip address', function () {
    $audience = Audience::factory()->create();
    $issued = TeamApiKey::issue($audience->team, 'Production');

    $this->withToken($issued['token'])
        ->postJson(route('api.v1.audiences.subscribers.store', $audience->uuid), [
            'email' => 'ada@example.com',
            'consent_ip' => 'not-an-ip',
        ])
        ->assertJsonValidationErrors(['consent_ip' => 'The consent ip field must be a valid IP address.']);

    expect($audience->subscribers()->exists())->toBeFalse();
});

test('the api accepts only audience languages and falls back to the default', function () {
    $audience = Audience::factory()->create([
        'allowed_languages' => [SubscriberLanguage::Indonesian->value],
        'default_language' => SubscriberLanguage::Indonesian,
    ]);
    $issued = TeamApiKey::issue($audience->team, 'Production');

    $this->withToken($issued['token'])
        ->postJson(route('api.v1.audiences.subscribers.store', $audience->uuid), [
            'email' => 'default@example.com',
        ])
        ->assertOk()
        ->assertJsonPath('data.language', 'id');

    $this->withToken($issued['token'])
        ->postJson(route('api.v1.audiences.subscribers.store', $audience->uuid), [
            'email' => 'unsupported@example.com',
            'language' => SubscriberLanguage::English->value,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['language']);

    expect($audience->subscribers()->count())->toBe(1);
});

test('the api uses an audience double opt-in transactional email', function () {
    $audience = Audience::factory()->create();
    TeamEmailIntegration::factory()->for($audience->team)->ses()->create();
    $transactionalEmail = TransactionalEmail::factory()
        ->for($audience->team)
        ->published()
        ->create(['html' => '<a href="{{ confirmation_url }}">Confirm</a>']);
    $audience->update([
        'double_opt_in' => true,
        'double_opt_in_email_id' => $transactionalEmail->id,
    ]);
    $issued = TeamApiKey::issue($audience->team, 'Production');
    Event::fake([SubscriberLifecycleOccurred::class]);
    Queue::fake();

    $this->withToken($issued['token'])
        ->postJson(route('api.v1.audiences.subscribers.store', $audience->uuid), [
            'email' => 'ada@example.com',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'subscribed');

    $subscriber = $audience->subscribers()->sole();
    $delivery = $transactionalEmail->deliveries()->sole();

    expect($subscriber->subscribed_at)->toBeNull()
        ->and($delivery->team_api_key_id)->toBeNull()
        ->and($delivery->to_address)->toBe('ada@example.com');

    Queue::assertPushed(
        SendTransactionalEmailDelivery::class,
        fn (SendTransactionalEmailDelivery $job): bool => $job->deliveryId === $delivery->id,
    );
    Event::assertNotDispatched(SubscriberLifecycleOccurred::class);
});

test('the api sends a pending subscriber a fresh confirmation email', function () {
    $audience = Audience::factory()->create();
    TeamEmailIntegration::factory()->for($audience->team)->ses()->create();
    $transactionalEmail = TransactionalEmail::factory()->for($audience->team)->published()->create();
    $audience->update([
        'double_opt_in' => true,
        'double_opt_in_email_id' => $transactionalEmail->id,
    ]);
    $subscriber = Subscriber::factory()->for($audience)->pendingConfirmation()->create(['email' => 'ada@example.com']);
    $issued = TeamApiKey::issue($audience->team, 'Production');
    Queue::fake([SendTransactionalEmailDelivery::class]);

    $this->withToken($issued['token'])
        ->postJson(route('api.v1.audiences.subscribers.store', $audience->uuid), [
            'email' => 'ada@example.com',
        ])
        ->assertOk()
        ->assertJsonPath('data.subscribed_at', null);

    expect($transactionalEmail->deliveries()->sole()->to_address)->toBe('ada@example.com')
        ->and($audience->subscribers()->sole()->is($subscriber))->toBeTrue();
    Queue::assertPushed(SendTransactionalEmailDelivery::class, 1);
});

test('an api double opt-in signup is kept when the workspace cannot send', function () {
    $audience = Audience::factory()->create();
    $transactionalEmail = TransactionalEmail::factory()->for($audience->team)->published()->create();
    $audience->update([
        'double_opt_in' => true,
        'double_opt_in_email_id' => $transactionalEmail->id,
    ]);
    $issued = TeamApiKey::issue($audience->team, 'Production');
    Queue::fake();

    $this->withToken($issued['token'])
        ->postJson(route('api.v1.audiences.subscribers.store', $audience->uuid), [
            'email' => 'ada@example.com',
        ])
        ->assertOk()
        ->assertJsonPath('data.email', 'ada@example.com')
        ->assertJsonPath('data.subscribed_at', null);

    expect($audience->subscribers()->sole()->subscribed_at)->toBeNull();

    Queue::assertNothingPushed();
});

test('an api subscribe on a single opt-in audience keeps an opted-out member unsubscribed', function () {
    $audience = Audience::factory()->create(['double_opt_in' => false]);
    $subscriber = Subscriber::factory()->for($audience)->unsubscribed()->create(['email' => 'ada@example.com']);
    $issued = TeamApiKey::issue($audience->team, 'Production');
    Event::fake([SubscriberLifecycleOccurred::class]);

    $this->withToken($issued['token'])
        ->postJson(route('api.v1.audiences.subscribers.store', $audience->uuid), [
            'email' => 'ada@example.com',
            'consent_text' => 'Signed up during checkout',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'unsubscribed')
        ->assertJsonPath('data.pending_confirmation', true)
        ->assertJsonPath('data.subscribed_at', null);

    expect($subscriber->fresh()->status)->toBe(SubscriberStatus::Unsubscribed)
        ->and($subscriber->fresh()->unsubscribed_at)->not->toBeNull();
    Event::assertNotDispatched(SubscriberLifecycleOccurred::class);
});

test('an api resubscribe on a double opt-in audience keeps the opt-out until the emailed link starts the resubscribed automation', function () {
    $audience = Audience::factory()->create();
    TeamEmailIntegration::factory()->for($audience->team)->create();
    $transactionalEmail = TransactionalEmail::factory()->for($audience->team)->published()->create([
        'html' => '<a href="{{ confirmation_url }}">Confirm</a>',
    ]);
    $audience->update(['double_opt_in' => true, 'double_opt_in_email_id' => $transactionalEmail->id]);
    $subscriber = Subscriber::factory()->for($audience)->unsubscribed()->create(['email' => 'ada@example.com']);
    $welcome = Automation::factory()->active()->for($audience->team)->triggeredBy(AutomationTrigger::Subscribed)->create();
    $winBack = Automation::factory()->active()->for($audience->team)->triggeredBy(AutomationTrigger::Resubscribed)->create();
    $issued = TeamApiKey::issue($audience->team, 'Production');
    Queue::fake();

    $this->withToken($issued['token'])
        ->postJson(route('api.v1.audiences.subscribers.store', $audience->uuid), ['email' => 'ada@example.com'])
        ->assertOk()
        ->assertJsonPath('data.status', 'unsubscribed')
        ->assertJsonPath('data.pending_confirmation', true)
        ->assertJsonPath('data.subscribed_at', null);

    expect($subscriber->fresh()->status)->toBe(SubscriberStatus::Unsubscribed)
        ->and($subscriber->fresh()->unsubscribed_at)->not->toBeNull();

    preg_match('/href="([^"]+)"/', $transactionalEmail->deliveries()->sole()->html, $link);
    $this->get(html_entity_decode($link[1]))
        ->assertInertia(fn (Assert $page) => $page->where('canConfirm', true));
    $this->post(html_entity_decode($link[1]))->assertRedirect();

    $subscriber->refresh();

    expect($subscriber->isOptedIn())->toBeTrue()
        ->and($subscriber->unsubscribed_at)->toBeNull()
        ->and($subscriber->resubscribe_requested_at)->toBeNull()
        ->and($winBack->runs()->where('subscriber_id', $subscriber->id)->exists())->toBeTrue()
        ->and($welcome->runs()->where('subscriber_id', $subscriber->id)->exists())->toBeFalse();
});

test('the api reports a confirmed member as not pending confirmation', function () {
    $subscriber = Subscriber::factory()->create();
    $issued = TeamApiKey::issue($subscriber->audience->team, 'Production');

    $this->withToken($issued['token'])
        ->postJson(route('api.v1.audiences.subscribers.store', $subscriber->audience->uuid), ['email' => $subscriber->email])
        ->assertOk()
        ->assertJsonPath('data.pending_confirmation', false);
});

test('the api unsubscribe operation is opaque and idempotent', function () {
    $subscriber = Subscriber::factory()->create();
    $audience = $subscriber->audience;
    $issued = TeamApiKey::issue($audience->team, 'Production');
    Event::fake([SubscriberLifecycleOccurred::class]);

    $this->withToken($issued['token'])
        ->deleteJson(route('api.v1.audiences.subscribers.destroy', $audience->uuid), [
            'email' => $subscriber->email,
        ])
        ->assertOk()
        ->assertExactJson(['data' => ['email' => $subscriber->email, 'status' => 'unsubscribed']]);

    $unsubscribedAt = $subscriber->fresh()->unsubscribed_at;

    $this->withToken($issued['token'])
        ->deleteJson(route('api.v1.audiences.subscribers.destroy', $audience->uuid), [
            'email' => $subscriber->email,
        ])
        ->assertOk();

    $this->withToken($issued['token'])
        ->deleteJson(route('api.v1.audiences.subscribers.destroy', $audience->uuid), [
            'email' => 'unknown@example.com',
        ])
        ->assertOk()
        ->assertExactJson(['data' => ['email' => 'unknown@example.com', 'status' => 'unsubscribed']]);

    expect($subscriber->fresh()->unsubscribed_at?->equalTo($unsubscribedAt))->toBeTrue();
    Event::assertDispatchedTimes(SubscriberLifecycleOccurred::class, 1);
});

test('the api returns 422 for attributes on an audience without custom fields', function () {
    $audience = Audience::factory()->create();
    $issued = TeamApiKey::issue($audience->team, 'Production');

    $this->withToken($issued['token'])
        ->postJson(route('api.v1.audiences.subscribers.store', $audience->uuid), [
            'email' => 'ada@example.com',
            'attributes' => ['first_name' => 'Mallory', 'nested' => ['x' => 1]],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['attributes']);

    expect($audience->subscribers()->count())->toBe(0);
});

test('the api stores date fields as Y-m-d', function () {
    $audience = Audience::factory()->create();
    $audience->audienceAttributes()->create([
        'name' => 'Birthday',
        'key' => 'birthday',
        'type' => AudienceAttributeType::Date,
    ]);
    $issued = TeamApiKey::issue($audience->team, 'Production');

    $this->withToken($issued['token'])
        ->postJson(route('api.v1.audiences.subscribers.store', $audience->uuid), [
            'email' => 'ada@example.com',
            'attributes' => ['birthday' => '31.12.2026'],
        ])
        ->assertOk();

    expect($audience->subscribers()->sole()->attribute_values)->toBe(['birthday' => '2026-12-31']);
});

test('subscriber endpoints enforce key team scope and configured attributes', function () {
    $audience = Audience::factory()->create();
    $audience->audienceAttributes()->create([
        'name' => 'Company',
        'key' => 'company',
        'type' => AudienceAttributeType::Text,
        'position' => 0,
        'required' => true,
    ]);
    $otherAudience = Audience::factory()->create();
    $issued = TeamApiKey::issue($audience->team, 'Production');

    $this->withToken($issued['token'])
        ->postJson(route('api.v1.audiences.subscribers.store', $audience->uuid), [
            'email' => 'ada@example.com',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['attributes', 'attributes.company']);

    $this->withToken($issued['token'])
        ->postJson(route('api.v1.audiences.subscribers.store', $audience->uuid), [
            'email' => 'ada@example.com',
            'language' => 'not-a-language',
            'attributes' => ['company' => 'Analytical Engines'],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['language']);

    $this->withToken($issued['token'])
        ->postJson(route('api.v1.audiences.subscribers.store', $audience->uuid), [
            'email' => 'ada@example.com',
            'attributes' => ['company' => 'Analytical Engines'],
        ])
        ->assertOk();

    expect($audience->subscribers()->sole()->attribute_values)->toBe([
        'company' => 'Analytical Engines',
    ]);

    $this->withToken($issued['token'])
        ->postJson(route('api.v1.audiences.subscribers.store', $otherAudience->uuid), [
            'email' => 'ada@example.com',
        ])
        ->assertNotFound();
});

test('subscriber endpoints answer a malformed audience id with 404 before any query uses it', function (string $method, string $route) {
    $audience = Audience::factory()->create();
    $issued = TeamApiKey::issue($audience->team, 'Production');
    $bindings = [];
    DB::listen(function (QueryExecuted $query) use (&$bindings): void {
        array_push($bindings, ...$query->bindings);
    });

    $this->withToken($issued['token'])
        ->json($method, route($route, 'not-a-uuid'), ['email' => 'ada@example.com'])
        ->assertNotFound();

    expect($bindings)->not->toContain('not-a-uuid');
})->with([
    'subscribe' => ['POST', 'api.v1.audiences.subscribers.store'],
    'unsubscribe' => ['DELETE', 'api.v1.audiences.subscribers.destroy'],
]);

test('subscriber endpoints reject missing and invalid api keys', function () {
    $audience = Audience::factory()->create();

    $this->postJson(route('api.v1.audiences.subscribers.store', $audience->uuid), [
        'email' => 'ada@example.com',
    ])->assertUnauthorized();

    $this->withToken('invalid')
        ->deleteJson(route('api.v1.audiences.subscribers.destroy', $audience->uuid), [
            'email' => 'ada@example.com',
        ])
        ->assertUnauthorized();
});

test('the api records the consent text, time and request ip', function () {
    $audience = Audience::factory()->create(['double_opt_in' => false]);
    $issued = TeamApiKey::issue($audience->team, 'Production');
    $this->freezeSecond();

    $this->withToken($issued['token'])
        ->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])
        ->postJson(route('api.v1.audiences.subscribers.store', $audience->uuid), [
            'email' => 'ada@example.com',
        ])
        ->assertOk();

    $subscriber = $audience->subscribers()->sole();

    expect($subscriber->consent_text)->toBe('Marketing consent confirmed via API.')
        ->and($subscriber->consented_at?->equalTo(now()))->toBeTrue()
        ->and($subscriber->consent_ip)->toBe('203.0.113.7');
});

test('the api rejects consent text longer than 1000 characters', function () {
    $audience = Audience::factory()->create();
    $issued = TeamApiKey::issue($audience->team, 'Production');

    $this->withToken($issued['token'])
        ->postJson(route('api.v1.audiences.subscribers.store', $audience->uuid), [
            'email' => 'ada@example.com',
            'consent_text' => str_repeat('a', 1001),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['consent_text' => 'The consent text field must not be greater than 1000 characters.']);

    expect($audience->subscribers()->count())->toBe(0);
});

test('subscriber endpoints validate the email address', function (string $method, string $routeName, array $payload, string $message) {
    $subscriber = Subscriber::factory()->create();
    $audience = $subscriber->audience;
    $issued = TeamApiKey::issue($audience->team, 'Production');

    $this->withToken($issued['token'])
        ->json($method, route($routeName, $audience->uuid), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email' => $message]);

    expect($audience->subscribers()->sole()->status)->toBe(SubscriberStatus::Subscribed);
})->with([
    'subscribe without email' => ['POST', 'api.v1.audiences.subscribers.store', [], 'The email field is required.'],
    'subscribe with an invalid email' => ['POST', 'api.v1.audiences.subscribers.store', ['email' => 'not-an-email'], 'The email field must be a valid email address.'],
    'unsubscribe without email' => ['DELETE', 'api.v1.audiences.subscribers.destroy', [], 'The email field is required.'],
    'unsubscribe with an invalid email' => ['DELETE', 'api.v1.audiences.subscribers.destroy', ['email' => 'not-an-email'], 'The email field must be a valid email address.'],
]);

test('the api cannot unsubscribe a member of another workspace audience', function () {
    $subscriber = Subscriber::factory()->create();
    $issued = TeamApiKey::issue(Audience::factory()->create()->team, 'Production');

    $this->withToken($issued['token'])
        ->deleteJson(route('api.v1.audiences.subscribers.destroy', $subscriber->audience->uuid), [
            'email' => $subscriber->email,
        ])
        ->assertNotFound();

    expect($subscriber->fresh()->status)->toBe(SubscriberStatus::Subscribed);
});

test('a revoked api key can no longer subscribe or unsubscribe', function () {
    $subscriber = Subscriber::factory()->create();
    $audience = $subscriber->audience;
    $issued = TeamApiKey::issue($audience->team, 'Production');
    $issued['key']->delete();

    $this->withToken($issued['token'])
        ->postJson(route('api.v1.audiences.subscribers.store', $audience->uuid), ['email' => 'ada@example.com'])
        ->assertUnauthorized();

    $this->withToken($issued['token'])
        ->deleteJson(route('api.v1.audiences.subscribers.destroy', $audience->uuid), ['email' => $subscriber->email])
        ->assertUnauthorized();

    expect($audience->subscribers()->sole()->status)->toBe(SubscriberStatus::Subscribed);
});

test('the subscriber api allows each key 120 requests a minute', function () {
    $audience = Audience::factory()->create();
    $throttled = TeamApiKey::issue($audience->team, 'Throttled');
    $other = TeamApiKey::issue($audience->team, 'Other');
    $this->freezeTime();

    foreach (range(1, 120) as $attempt) {
        $this->withToken($throttled['token'])
            ->deleteJson(route('api.v1.audiences.subscribers.destroy', $audience->uuid), ['email' => 'ada@example.com'])
            ->assertOk();
    }

    $this->withToken($throttled['token'])
        ->postJson(route('api.v1.audiences.subscribers.store', $audience->uuid), ['email' => 'ada@example.com'])
        ->assertTooManyRequests();

    $this->withToken($other['token'])
        ->postJson(route('api.v1.audiences.subscribers.store', $audience->uuid), ['email' => 'ada@example.com'])
        ->assertOk();

    expect($audience->subscribers()->count())->toBe(1);
});
