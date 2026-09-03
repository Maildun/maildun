<?php

use App\Enums\AudienceAttributeType;
use App\Enums\AutomationTrigger;
use App\Enums\SubscriberSource;
use App\Enums\SubscriberStatus;
use App\Events\SubscriberLifecycleOccurred;
use App\Jobs\SendTransactionalEmailDelivery;
use App\Models\Audience;
use App\Models\Subscriber;
use App\Models\TeamApiKey;
use App\Models\TeamEmailIntegration;
use App\Models\TransactionalEmail;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

test('the api subscribes idempotently without replacing the shared contact profile', function () {
    $audience = Audience::factory()->create();
    $issued = TeamApiKey::issue($audience->team, 'Production');
    Event::fake([SubscriberLifecycleOccurred::class]);

    $this->withToken($issued['token'])
        ->postJson(route('api.v1.audiences.subscribers.store', $audience->uuid), [
            'email' => 'ADA@EXAMPLE.COM',
            'first_name' => 'Ada',
            'consent_text' => 'Signed up during checkout',
        ])
        ->assertOk()
        ->assertJsonPath('data.email', 'ada@example.com')
        ->assertJsonPath('data.status', 'subscribed');

    $subscriber = $audience->subscribers()->sole();

    expect($subscriber->source)->toBe(SubscriberSource::Api)
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
        ])
        ->assertOk()
        ->assertJsonPath('data.first_name', 'Ada');

    expect($audience->subscribers()->count())->toBe(1)
        ->and($audience->team->contacts()->sole()->first_name)->toBe('Ada');
    Event::assertDispatchedTimes(SubscriberLifecycleOccurred::class, 1);
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

test('the api resubscribes an unsubscribed member and fires the lifecycle event', function () {
    $subscriber = Subscriber::factory()->unsubscribed()->create();
    $issued = TeamApiKey::issue($subscriber->audience->team, 'Production');
    Event::fake([SubscriberLifecycleOccurred::class]);

    $this->withToken($issued['token'])
        ->postJson(route('api.v1.audiences.subscribers.store', $subscriber->audience->uuid), [
            'email' => $subscriber->email,
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'subscribed');

    expect($subscriber->fresh()->unsubscribed_at)->toBeNull()
        ->and($subscriber->fresh()->source)->toBe(SubscriberSource::Api);
    Event::assertDispatched(
        SubscriberLifecycleOccurred::class,
        fn (SubscriberLifecycleOccurred $event): bool => $event->trigger === AutomationTrigger::Resubscribed,
    );
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
        ->assertJsonPath('data.status', 'unsubscribed');

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
        ->assertJsonPath('data.status', 'unsubscribed');

    expect($subscriber->fresh()->unsubscribed_at?->equalTo($unsubscribedAt))->toBeTrue();
    Event::assertDispatchedTimes(SubscriberLifecycleOccurred::class, 1);
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
