<?php

use App\Enums\ConversionAttributionType;
use App\Enums\EmailDeliveryStatus;
use App\Enums\EmailStatus;
use App\Models\Audience;
use App\Models\CampaignSeries;
use App\Models\Email;
use App\Models\EmailConversionEvent;
use App\Models\EmailDelivery;
use App\Models\Subscriber;
use App\Models\Team;
use App\Models\TeamApiKey;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * A sent delivery of a campaign inside the given series.
 *
 * @param  array<string, mixed>  $attributes
 */
function conversionSeriesDelivery(CampaignSeries $campaignSeries, array $attributes = []): EmailDelivery
{
    $campaign = Email::factory()->for($campaignSeries->team)->for($campaignSeries, 'campaignSeries')->create([
        'status' => EmailStatus::Sent,
        'sent_at' => $attributes['sent_at'] ?? now()->subDay(),
    ]);

    return EmailDelivery::factory()->for($campaign)->create([
        'subscriber_id' => null,
        'email_address' => 'lead@example.com',
        'status' => EmailDeliveryStatus::Delivered,
        'sent_at' => now()->subDay(),
        ...$attributes,
    ]);
}

/**
 * @return array<string, mixed>
 */
function conversionPayload(array $overrides = []): array
{
    return [
        'event_id' => 'booking_01J9Y8PGT2',
        'event' => 'meeting_booked',
        'email' => 'lead@example.com',
        'occurred_at' => now()->toIso8601ZuluString(),
        ...$overrides,
    ];
}

test('a conversion is credited to the campaign of the latest click across eligible series', function () {
    $this->freezeSecond();
    $team = Team::factory()->create();
    $firstSeries = CampaignSeries::factory()->for($team)->tracksConversions()->create();
    $secondSeries = CampaignSeries::factory()->for($team)->tracksConversions()->create();
    conversionSeriesDelivery($firstSeries, [
        'email_address' => 'Lead@Example.com',
        'sent_at' => now()->subDays(6),
        'first_clicked_at' => now()->subDays(5),
        'last_clicked_at' => now()->subDays(5),
    ]);
    $latestClick = conversionSeriesDelivery($secondSeries, [
        'sent_at' => now()->subDays(3),
        'first_clicked_at' => now()->subDays(2),
        'last_clicked_at' => now()->subDays(2),
    ]);
    conversionSeriesDelivery($firstSeries, ['sent_at' => now()->subDay()]);
    $issued = TeamApiKey::issue($team, 'Production');

    $this->withToken($issued['token'])
        ->postJson(route('api.v1.conversions.store'), conversionPayload([
            'email' => 'LEAD@example.COM',
            'value_minor' => 250000,
            'currency' => 'usd',
            'metadata' => ['booking_id' => 'demo_1842'],
        ]))
        ->assertCreated()
        ->assertJsonPath('data.event_id', 'booking_01J9Y8PGT2')
        ->assertJsonPath('data.email', 'lead@example.com')
        ->assertJsonPath('data.currency', 'USD')
        ->assertJsonPath('data.attribution.type', 'click')
        ->assertJsonPath('data.attribution.campaign_series', $secondSeries->uuid)
        ->assertJsonPath('data.attribution.campaign', $latestClick->email->uuid)
        ->assertJsonPath('data.attribution.delivery', $latestClick->uuid)
        ->assertJsonPath('meta.idempotent_replay', false);

    expect($team->conversionEvents()->sole())
        ->attribution_type->toBe(ConversionAttributionType::Click)
        ->campaign_series_id->toBe($secondSeries->id)
        ->email_id->toBe($latestClick->email_id)
        ->email_delivery_id->toBe($latestClick->id)
        ->value_minor->toBe(250000)
        ->metadata->toBe(['booking_id' => 'demo_1842']);
});

test('a click after the conversion does not earn credit but an earlier click on the same delivery does', function () {
    $this->freezeSecond();
    $team = Team::factory()->create();
    $campaignSeries = CampaignSeries::factory()->for($team)->tracksConversions()->create();
    $earlierClick = conversionSeriesDelivery($campaignSeries, [
        'sent_at' => now()->subDays(4),
        'first_clicked_at' => now()->subDays(3),
        'last_clicked_at' => now()->addHour(),
    ]);
    conversionSeriesDelivery($campaignSeries, [
        'sent_at' => now()->subDays(2),
        'first_clicked_at' => now()->addMinutes(30),
        'last_clicked_at' => now()->addMinutes(30),
    ]);
    $issued = TeamApiKey::issue($team, 'Production');

    $this->withToken($issued['token'])
        ->postJson(route('api.v1.conversions.store'), conversionPayload())
        ->assertCreated()
        ->assertJsonPath('data.attribution.type', 'click')
        ->assertJsonPath('data.attribution.campaign', $earlierClick->email->uuid)
        ->assertJsonPath('data.attribution.touched_at', now()->subDays(3)->toISOString());
});

test('a recent delivery without a click credits the series as assisted only', function () {
    $this->freezeSecond();
    $team = Team::factory()->create();
    $campaignSeries = CampaignSeries::factory()->for($team)->tracksConversions()->create();
    conversionSeriesDelivery($campaignSeries, ['sent_at' => now()->subDays(3)]);
    $latestDelivery = conversionSeriesDelivery($campaignSeries, [
        'sent_at' => now()->subDay(),
        'first_opened_at' => now()->subHours(20),
    ]);
    $issued = TeamApiKey::issue($team, 'Production');

    $this->withToken($issued['token'])
        ->postJson(route('api.v1.conversions.store'), conversionPayload())
        ->assertCreated()
        ->assertJsonPath('data.attribution.type', 'assisted')
        ->assertJsonPath('data.attribution.campaign_series', $campaignSeries->uuid)
        ->assertJsonPath('data.attribution.delivery', $latestDelivery->uuid);
});

test('the attribution window boundary is inclusive', function (int $windowDays, int $secondsBeforeWindow, string $expectedType) {
    $this->freezeSecond();
    $team = Team::factory()->create();
    $campaignSeries = CampaignSeries::factory()->for($team)->tracksConversions('meeting_booked', $windowDays)->create();
    conversionSeriesDelivery($campaignSeries, [
        'sent_at' => now()->subDays($windowDays)->subSeconds($secondsBeforeWindow),
    ]);
    $issued = TeamApiKey::issue($team, 'Production');

    $this->withToken($issued['token'])
        ->postJson(route('api.v1.conversions.store'), conversionPayload())
        ->assertCreated()
        ->assertJsonPath('data.attribution.type', $expectedType);
})->with([
    'sent exactly 30 days before' => [30, 0, 'assisted'],
    'sent 30 days and 1 second before' => [30, 1, 'unattributed'],
    'sent exactly 7 days before a 7 day window' => [7, 0, 'assisted'],
    'sent 7 days and 1 second before a 7 day window' => [7, 1, 'unattributed'],
]);

test('a conversion with no eligible touch is stored unattributed with its subscriber', function () {
    $this->freezeSecond();
    $team = Team::factory()->create();
    $audience = Audience::factory()->for($team)->create();
    $subscriber = Subscriber::factory()->for($audience)->create(['email' => 'lead@example.com']);
    $trackedSeries = CampaignSeries::factory()->for($team)->tracksConversions()->create();
    $otherEventSeries = CampaignSeries::factory()->for($team)->tracksConversions('trial_started')->create();
    conversionSeriesDelivery($trackedSeries, ['sent_at' => now()->addHour()]);
    conversionSeriesDelivery($otherEventSeries, [
        'first_clicked_at' => now()->subHour(),
        'last_clicked_at' => now()->subHour(),
    ]);
    conversionSeriesDelivery($trackedSeries, [
        'status' => EmailDeliveryStatus::Bounced,
        'sent_at' => now()->subDay(),
    ]);
    $deletedCampaign = conversionSeriesDelivery($trackedSeries, [
        'first_clicked_at' => now()->subHour(),
        'last_clicked_at' => now()->subHour(),
    ]);
    $deletedCampaign->email->delete();
    $issued = TeamApiKey::issue($team, 'Production');

    $this->withToken($issued['token'])
        ->postJson(route('api.v1.conversions.store'), conversionPayload())
        ->assertCreated()
        ->assertJsonPath('data.attribution.type', 'unattributed')
        ->assertJsonPath('data.attribution.campaign_series', null);

    expect($team->conversionEvents()->sole())
        ->subscriber_id->toBe($subscriber->id)
        ->campaign_series_id->toBeNull();
});

test('an explicit series restricts attribution to that series', function () {
    $this->freezeSecond();
    $team = Team::factory()->create();
    $explicitSeries = CampaignSeries::factory()->for($team)->create();
    $otherSeries = CampaignSeries::factory()->for($team)->tracksConversions()->create();
    conversionSeriesDelivery($otherSeries, [
        'first_clicked_at' => now()->subHour(),
        'last_clicked_at' => now()->subHour(),
    ]);
    $issued = TeamApiKey::issue($team, 'Production');

    $this->withToken($issued['token'])
        ->postJson(route('api.v1.conversions.store'), conversionPayload([
            'campaign_series' => $explicitSeries->uuid,
        ]))
        ->assertCreated()
        ->assertJsonPath('data.attribution.type', 'explicit')
        ->assertJsonPath('data.attribution.campaign_series', $explicitSeries->uuid)
        ->assertJsonPath('data.attribution.campaign', null);

    expect($team->conversionEvents()->sole()->requested_campaign_series_id)->toBe($explicitSeries->id);
});

test('an explicit series still credits the campaign clicked inside it', function () {
    $this->freezeSecond();
    $team = Team::factory()->create();
    $explicitSeries = CampaignSeries::factory()->for($team)->create();
    $otherSeries = CampaignSeries::factory()->for($team)->tracksConversions()->create();
    $explicitClick = conversionSeriesDelivery($explicitSeries, [
        'sent_at' => now()->subDays(2),
        'first_clicked_at' => now()->subDays(2),
        'last_clicked_at' => now()->subDays(2),
    ]);
    conversionSeriesDelivery($otherSeries, [
        'first_clicked_at' => now()->subHour(),
        'last_clicked_at' => now()->subHour(),
    ]);
    $issued = TeamApiKey::issue($team, 'Production');

    $this->withToken($issued['token'])
        ->postJson(route('api.v1.conversions.store'), conversionPayload([
            'campaign_series' => $explicitSeries->uuid,
        ]))
        ->assertCreated()
        ->assertJsonPath('data.attribution.type', 'click')
        ->assertJsonPath('data.attribution.campaign_series', $explicitSeries->uuid)
        ->assertJsonPath('data.attribution.campaign', $explicitClick->email->uuid)
        ->assertJsonPath('data.attribution.delivery', $explicitClick->uuid);
});

test('conversions recorded through the api show up in the series report by attribution type', function () {
    $this->freezeSecond();
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $campaignSeries = CampaignSeries::factory()->for($team)->tracksConversions()->create(['value_currency' => 'USD']);
    $clicked = conversionSeriesDelivery($campaignSeries, [
        'email_address' => 'clicker@example.com',
        'first_clicked_at' => now()->subHours(20),
        'last_clicked_at' => now()->subHours(20),
    ]);
    conversionSeriesDelivery($campaignSeries, ['email_address' => 'reader@example.com']);
    $issued = TeamApiKey::issue($team, 'Production');

    foreach ([
        ['event_id' => 'click_1', 'email' => 'clicker@example.com', 'value_minor' => 5000, 'currency' => 'USD'],
        ['event_id' => 'assisted_1', 'email' => 'reader@example.com'],
        ['event_id' => 'explicit_1', 'email' => 'walkin@example.com', 'campaign_series' => $campaignSeries->uuid],
        ['event_id' => 'unattributed_1', 'email' => 'stranger@example.com'],
    ] as $overrides) {
        $this->withToken($issued['token'])
            ->postJson(route('api.v1.conversions.store'), conversionPayload($overrides))
            ->assertCreated();
    }

    $this->actingAs($user)
        ->get(route('campaign_series.show', [$team, $campaignSeries]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('report.summary.conversions', 3)
            ->where('report.summary.attributed_conversions', 1)
            ->where('report.summary.assisted_conversions', 1)
            ->where('report.summary.explicit_conversions', 1)
            ->where('report.summary.conversion_value', [['currency' => 'USD', 'value_minor' => 5000]])
            ->where('report.campaigns', fn ($campaigns) => collect($campaigns)->firstWhere('uuid', $clicked->email->uuid)['conversions'] === 1
                && collect($campaigns)->sum('conversions') === 1));
});

test('an identical retry returns the stored event and a changed retry conflicts', function () {
    $this->freezeSecond();
    $team = Team::factory()->create();
    $issued = TeamApiKey::issue($team, 'Production');
    $payload = conversionPayload(['value_minor' => 1000, 'currency' => 'USD']);

    $eventId = $this->withToken($issued['token'])
        ->postJson(route('api.v1.conversions.store'), $payload)
        ->assertCreated()
        ->json('data.id');

    $this->travel(1)->minutes();

    $this->withToken($issued['token'])
        ->postJson(route('api.v1.conversions.store'), $payload)
        ->assertOk()
        ->assertJsonPath('data.id', $eventId)
        ->assertJsonPath('meta.idempotent_replay', true);

    $this->withToken($issued['token'])
        ->postJson(route('api.v1.conversions.store'), [...$payload, 'value_minor' => 2000])
        ->assertConflict()
        ->assertJsonPath('code', 'idempotency_conflict');

    expect($team->conversionEvents()->sole())
        ->uuid->toBe($eventId)
        ->value_minor->toBe(1000);
});

test('an event without occurred_at can be retried safely', function () {
    $team = Team::factory()->create();
    $issued = TeamApiKey::issue($team, 'Production');
    $payload = conversionPayload(['occurred_at' => null]);

    $this->withToken($issued['token'])
        ->postJson(route('api.v1.conversions.store'), $payload)
        ->assertCreated();

    $this->travel(5)->seconds();

    $this->withToken($issued['token'])
        ->postJson(route('api.v1.conversions.store'), $payload)
        ->assertOk();

    expect($team->conversionEvents()->count())->toBe(1);
});

test('workspaces are isolated from each other', function () {
    $this->freezeSecond();
    $team = Team::factory()->create();
    $otherTeam = Team::factory()->create();
    $foreignSeries = CampaignSeries::factory()->for($otherTeam)->tracksConversions()->create();
    conversionSeriesDelivery($foreignSeries, [
        'first_clicked_at' => now()->subHour(),
        'last_clicked_at' => now()->subHour(),
    ]);
    EmailConversionEvent::factory()->for($otherTeam)->create([
        'external_id' => 'booking_01J9Y8PGT2',
        'event_key' => 'trial_started',
    ]);
    $issued = TeamApiKey::issue($team, 'Production');

    $foreignSeriesResponse = $this->withToken($issued['token'])
        ->postJson(route('api.v1.conversions.store'), conversionPayload([
            'campaign_series' => $foreignSeries->uuid,
        ]))
        ->assertUnprocessable()
        ->json('errors.campaign_series');
    $missingSeriesResponse = $this->withToken($issued['token'])
        ->postJson(route('api.v1.conversions.store'), conversionPayload([
            'campaign_series' => (string) Str::uuid(),
        ]))
        ->assertUnprocessable()
        ->json('errors.campaign_series');

    expect($foreignSeriesResponse)->toBe($missingSeriesResponse)
        ->toBe(['The selected campaign series is invalid.']);

    $this->withToken($issued['token'])
        ->postJson(route('api.v1.conversions.store'), conversionPayload())
        ->assertCreated()
        ->assertJsonPath('data.attribution.type', 'unattributed');

    expect($team->conversionEvents()->count())->toBe(1)
        ->and($otherTeam->conversionEvents()->sole()->event_key)->toBe('trial_started');
});

test('conversions require a valid workspace api key', function () {
    $this->postJson(route('api.v1.conversions.store'), conversionPayload())
        ->assertUnauthorized();

    $this->withToken('maildun_live_invalid')
        ->postJson(route('api.v1.conversions.store'), conversionPayload())
        ->assertUnauthorized();

    $this->assertDatabaseCount('email_conversion_events', 0);
});

test('conversions have their own rate limit per api key', function () {
    $team = Team::factory()->create();
    $issued = TeamApiKey::issue($team, 'Production');
    $other = TeamApiKey::issue($team, 'Other');

    foreach (range(1, 60) as $attempt) {
        $this->withToken($issued['token'])
            ->postJson(route('api.v1.conversions.store'), conversionPayload([
                'event_id' => "booking_{$attempt}",
                'occurred_at' => null,
            ]))
            ->assertCreated();
    }

    $this->withToken($issued['token'])
        ->postJson(route('api.v1.conversions.store'), conversionPayload(['occurred_at' => null]))
        ->assertTooManyRequests();

    $this->withToken($other['token'])
        ->postJson(route('api.v1.conversions.store'), conversionPayload(['occurred_at' => null]))
        ->assertCreated();
});

test('conversion payloads are validated', function (array $overrides, string $field, string $message) {
    $this->freezeSecond();
    $issued = TeamApiKey::issue(Team::factory()->create(), 'Production');

    $this->withToken($issued['token'])
        ->postJson(route('api.v1.conversions.store'), conversionPayload($overrides))
        ->assertUnprocessable()
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonPath("errors.{$field}.0", $message);

    $this->assertDatabaseCount('email_conversion_events', 0);
})->with([
    'missing event id' => [['event_id' => null], 'event_id', 'The event id field is required.'],
    'uppercase event key' => [['event' => 'Meeting-Booked'], 'event', 'The event must be lowercase letters, numbers, and underscores, starting with a letter, up to 64 characters.'],
    'invalid email' => [['email' => 'not-an-email'], 'email', 'The email field must be a valid email address.'],
    'future occurrence' => [['occurred_at' => Carbon::parse('2999-01-01')->toIso8601ZuluString()], 'occurred_at', 'The occurred at time cannot be in the future.'],
    'negative value' => [['value_minor' => -1, 'currency' => 'USD'], 'value_minor', 'The value minor field must be at least 0.'],
    'value without currency' => [['value_minor' => 100], 'currency', 'The currency field is required when value minor is present.'],
    'malformed currency' => [['value_minor' => 100, 'currency' => 'US1'], 'currency', 'The currency must be a three-letter ISO 4217 code such as USD.'],
    'series that is not a uuid' => [['campaign_series' => 'q4-demos'], 'campaign_series', 'The campaign series field must be a valid UUID.'],
    'nested metadata' => [['metadata' => ['booking' => ['id' => 1]]], 'metadata', 'Metadata values must be strings, numbers, booleans, or null.'],
    'too many metadata keys' => [['metadata' => array_fill_keys(array_map(fn (int $index): string => "key_{$index}", range(1, 21)), 'x')], 'metadata', 'The metadata field must not have more than 20 items.'],
    'oversized metadata' => [['metadata' => ['notes' => str_repeat('x', 4100)]], 'metadata', 'Metadata may not be larger than 4096 bytes.'],
]);
