<?php

use App\Enums\AutomationTrigger;
use App\Enums\SubscriberStatus;
use App\Events\SubscriberLifecycleOccurred;
use App\Mcp\Servers\MaildunServer;
use App\Mcp\Tools\Subscribers\AddSubscriberTool;
use App\Mcp\Tools\Subscribers\ListSubscribersTool;
use App\Mcp\Tools\Subscribers\RemoveSubscriberTool;
use App\Mcp\Tools\Subscribers\ResubscribeSubscriberTool;
use App\Mcp\Tools\Subscribers\UnsubscribeSubscriberTool;
use App\Models\Audience;
use App\Models\Subscriber;
use App\Models\Team;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\Fluent\AssertableJson;

test('manages an audience membership through MCP', function () {
    Event::fake([SubscriberLifecycleOccurred::class]);
    $team = Team::factory()->create();
    $audience = Audience::factory()->for($team)->create();

    MaildunServer::tool(AddSubscriberTool::class, [
        'workspace' => $team->slug,
        'audience_uuid' => $audience->uuid,
        'email' => 'ada@example.com',
    ])->assertHasErrors(['consented to marketing email']);

    MaildunServer::tool(AddSubscriberTool::class, [
        'workspace' => $team->slug,
        'audience_uuid' => $audience->uuid,
        'email' => 'ada@example.com',
        'first_name' => 'Ada',
        'consent_confirmed' => true,
    ])->assertOk()->assertStructuredContent(fn (AssertableJson $json) => $json
        ->where('subscribed', true)
        ->where('subscriber.status', 'subscribed')
        ->where('subscriber.source', 'manual')
        ->etc());

    $subscriber = Subscriber::query()->whereBelongsTo($audience)->sole();
    Event::assertDispatched(SubscriberLifecycleOccurred::class, fn (SubscriberLifecycleOccurred $event): bool => $event->trigger === AutomationTrigger::Subscribed);

    MaildunServer::tool(UnsubscribeSubscriberTool::class, [
        'workspace' => $team->slug,
        'uuid' => $subscriber->uuid,
    ])->assertOk()->assertStructuredContent(fn (AssertableJson $json) => $json
        ->where('subscriber.status', 'unsubscribed')
        ->etc());

    MaildunServer::tool(AddSubscriberTool::class, [
        'workspace' => $team->slug,
        'audience_uuid' => $audience->uuid,
        'email' => 'ada@example.com',
        'consent_confirmed' => true,
    ])->assertHasErrors(['unsubscribed from']);

    expect($subscriber->refresh()->status)->toBe(SubscriberStatus::Unsubscribed);

    MaildunServer::tool(ListSubscribersTool::class, [
        'workspace' => $team->slug,
        'audience_uuid' => $audience->uuid,
        'status' => 'unsubscribed',
    ])->assertOk()->assertStructuredContent(fn (AssertableJson $json) => $json
        ->where('count', 1)
        ->where('subscribers.0.uuid', $subscriber->uuid)
        ->etc());

    MaildunServer::tool(ResubscribeSubscriberTool::class, [
        'workspace' => $team->slug,
        'uuid' => $subscriber->uuid,
        'consent_confirmed' => true,
    ])->assertOk()->assertStructuredContent(fn (AssertableJson $json) => $json
        ->where('subscriber.status', 'subscribed')
        ->etc());

    Event::assertDispatched(SubscriberLifecycleOccurred::class, fn (SubscriberLifecycleOccurred $event): bool => $event->trigger === AutomationTrigger::Resubscribed);

    MaildunServer::tool(RemoveSubscriberTool::class, [
        'workspace' => $team->slug,
        'uuid' => $subscriber->uuid,
        'confirm_email' => 'ada@example.com',
    ])->assertOk();

    expect(Subscriber::query()->whereKey($subscriber->id)->exists())->toBeFalse()
        ->and($team->contacts()->where('email', 'ada@example.com')->exists())->toBeTrue();
});

test('requires renewed consent to resubscribe', function () {
    $team = Team::factory()->create();
    $audience = Audience::factory()->for($team)->create();
    $subscriber = Subscriber::factory()->for($audience)->unsubscribed()->create();

    MaildunServer::tool(ResubscribeSubscriberTool::class, [
        'workspace' => $team->slug,
        'uuid' => $subscriber->uuid,
    ])->assertHasErrors(['consented again']);

    expect($subscriber->refresh()->status)->toBe(SubscriberStatus::Unsubscribed);
});

test('does not resolve subscribers from another workspace', function () {
    $team = Team::factory()->create();
    $otherSubscriber = Subscriber::factory()->create();

    MaildunServer::tool(UnsubscribeSubscriberTool::class, [
        'workspace' => $team->slug,
        'uuid' => $otherSubscriber->uuid,
    ])->assertHasErrors(['subscriber was not found in this workspace']);

    expect($otherSubscriber->refresh()->status)->toBe(SubscriberStatus::Subscribed);
});
