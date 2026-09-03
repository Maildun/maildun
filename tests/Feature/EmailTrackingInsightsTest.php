<?php

use App\Actions\Emails\BuildCampaignInsights;
use App\Enums\EmailStatus;
use App\Enums\EmailTrackingClassification;
use App\Enums\EmailTrackingEventType;
use App\Enums\EmailTrackingInsightDimension;
use App\Jobs\ProcessEmailTrackingEvent;
use App\Models\Email;
use App\Models\EmailDelivery;
use App\Models\EmailTrackingEvent;
use App\Models\EmailTrackingInsightAggregate;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('human tracking events project durable location network client and device insights', function () {
    $email = Email::factory()->create(['recipient_count' => 1]);
    $delivery = EmailDelivery::factory()->for($email)->create();
    $occurredAt = now()->subMinute()->startOfSecond();

    $first = EmailTrackingEvent::factory()->for($delivery, 'delivery')->create([
        'classification' => EmailTrackingClassification::Human,
        'classification_reason' => 'recognized_browser',
        'client_family' => 'Chrome',
        'device_type' => 'desktop',
        'is_bot' => false,
        'is_proxy' => false,
        'country_code' => 'US',
        'subdivision_code' => 'CA',
        'subdivision_name' => 'California',
        'city_name' => 'Mountain View',
        'network_asn' => 15169,
        'network_name' => 'Google LLC',
        'occurred_at' => $occurredAt,
    ]);
    $second = EmailTrackingEvent::factory()->for($delivery, 'delivery')->create([
        'classification' => EmailTrackingClassification::Human,
        'classification_reason' => 'recognized_browser',
        'client_family' => 'Chrome',
        'device_type' => 'desktop',
        'is_bot' => false,
        'is_proxy' => false,
        'country_code' => 'US',
        'subdivision_code' => 'CA',
        'subdivision_name' => 'California',
        'city_name' => 'Mountain View',
        'network_asn' => 15169,
        'network_name' => 'Google LLC',
        'occurred_at' => $occurredAt->copy()->addSecond(),
    ]);

    app()->call([(new ProcessEmailTrackingEvent($first->id)), 'handle']);
    app()->call([(new ProcessEmailTrackingEvent($second->id)), 'handle']);

    $dimensions = EmailTrackingInsightAggregate::query()
        ->where('email_id', $email->id)
        ->get()
        ->keyBy(fn (EmailTrackingInsightAggregate $aggregate): string => $aggregate->dimension->value.':'.$aggregate->dimension_key);

    expect($dimensions)->toHaveKeys([
        'classification:human',
        'country:US',
        'region:US:CA',
        'city:US:CA:mountain-view',
        'network:as15169',
        'client:chrome',
        'device:desktop',
    ])->and($dimensions['classification:human']->total_opens_count)->toBe(2)
        ->and($dimensions['classification:human']->unique_opens_count)->toBe(1)
        ->and($dimensions['country:US']->total_opens_count)->toBe(2)
        ->and($dimensions['country:US']->unique_opens_count)->toBe(1);
});

test('a human click becomes both a filtered click and open when the pixel was blocked', function () {
    $email = Email::factory()->create(['recipient_count' => 1]);
    $delivery = EmailDelivery::factory()->for($email)->create();
    $link = $email->links()->create([
        'url' => 'https://example.com/story',
        'url_hash' => hash('sha256', 'https://example.com/story'),
        'position' => 0,
    ]);
    $event = EmailTrackingEvent::factory()->for($delivery, 'delivery')->create([
        'type' => EmailTrackingEventType::Click,
        'email_link_id' => $link->id,
        'classification' => EmailTrackingClassification::Human,
        'classification_reason' => 'recognized_browser',
        'client_family' => 'Safari',
        'device_type' => 'mobile',
        'is_bot' => false,
        'is_proxy' => false,
    ]);

    app()->call([(new ProcessEmailTrackingEvent($event->id)), 'handle']);

    $human = EmailTrackingInsightAggregate::query()
        ->where('email_id', $email->id)
        ->where('classification', EmailTrackingClassification::Human)
        ->where('dimension', EmailTrackingInsightDimension::Classification)
        ->where('dimension_key', EmailTrackingClassification::Human->value)
        ->firstOrFail();

    expect($human->total_opens_count)->toBe(1)
        ->and($human->unique_opens_count)->toBe(1)
        ->and($human->total_clicks_count)->toBe(1)
        ->and($human->unique_clicks_count)->toBe(1);
});

test('campaign insights separate human bot proxy and unknown engagement', function () {
    $email = Email::factory()->create(['recipient_count' => 4]);

    foreach (EmailTrackingClassification::cases() as $classification) {
        $delivery = EmailDelivery::factory()->for($email)->create();
        $event = EmailTrackingEvent::factory()->for($delivery, 'delivery')->create([
            'classification' => $classification,
            'classification_reason' => 'test',
            'client_family' => $classification->label(),
            'device_type' => $classification === EmailTrackingClassification::Human ? 'desktop' : 'server',
            'is_bot' => $classification === EmailTrackingClassification::Bot,
            'is_proxy' => $classification === EmailTrackingClassification::PrivacyProxy,
            'country_code' => 'US',
        ]);

        app()->call([(new ProcessEmailTrackingEvent($event->id)), 'handle']);
    }

    $insights = app(BuildCampaignInsights::class)->handle($email);

    expect($insights['available'])->toBeTrue()
        ->and($insights['human'])->toMatchArray([
            'opened' => 1,
            'clicked' => 0,
            'open_rate' => 25.0,
            'click_rate' => 0.0,
        ])
        ->and($insights['traffic'])->toHaveCount(4)
        ->and(collect($insights['traffic'])->pluck('classification')->all())->toBe([
            'human',
            'bot',
            'privacy_proxy',
            'unknown',
        ])
        ->and($insights['locations']['countries'][0])->toMatchArray([
            'key' => 'US',
            'unique_opens' => 1,
        ]);
});

test('processed tracking event payloads expire without deleting pending events or insights', function () {
    $email = Email::factory()->create();
    $delivery = EmailDelivery::factory()->for($email)->create();
    $old = EmailTrackingEvent::factory()->for($delivery, 'delivery')->create([
        'classification' => EmailTrackingClassification::Human,
        'client_family' => 'Chrome',
        'device_type' => 'desktop',
        'occurred_at' => now()->subDays(91),
    ]);
    $recent = EmailTrackingEvent::factory()->for($delivery, 'delivery')->create([
        'processed_at' => now()->subDays(10),
        'occurred_at' => now()->subDays(10),
    ]);
    $pending = EmailTrackingEvent::factory()->for($delivery, 'delivery')->create([
        'processed_at' => null,
        'occurred_at' => now()->subDays(91),
    ]);

    app()->call([(new ProcessEmailTrackingEvent($old->id)), 'handle']);

    expect(EmailTrackingInsightAggregate::query()->where('email_id', $email->id)->exists())
        ->toBeTrue();

    $this->artisan('emails:prune-tracking')->assertSuccessful();

    expect($old->fresh())->toBeNull()
        ->and($recent->fresh())->not->toBeNull()
        ->and($pending->fresh())->not->toBeNull()
        ->and(EmailTrackingInsightAggregate::query()->where('email_id', $email->id)->exists())
        ->toBeTrue();
});

test('the campaign report exposes filtered insights and privacy details', function () {
    $user = User::factory()->create();
    $email = Email::factory()->for($user->currentTeam)->create([
        'status' => EmailStatus::Sent,
        'recipient_count' => 1,
        'send_started_at' => now()->subMinute(),
    ]);
    $delivery = EmailDelivery::factory()->for($email)->create();
    $event = EmailTrackingEvent::factory()->for($delivery, 'delivery')->create([
        'classification' => EmailTrackingClassification::Human,
        'classification_reason' => 'recognized_browser',
        'client_family' => 'Chrome',
        'device_type' => 'desktop',
        'is_bot' => false,
        'is_proxy' => false,
        'country_code' => 'US',
    ]);

    app()->call([(new ProcessEmailTrackingEvent($event->id)), 'handle']);

    $this->actingAs($user)
        ->get(route('emails.show', [$user->currentTeam, $email]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('emails/show')
            ->where('insights.available', true)
            ->where('insights.human.opened', 1)
            ->where('insights.privacy.raw_ip_stored', false)
            ->where('insights.privacy.event_retention_days', 90)
            ->where('insights.attribution.label', 'DB-IP Lite'));
});
