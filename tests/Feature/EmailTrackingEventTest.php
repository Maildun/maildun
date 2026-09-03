<?php

use App\Actions\Emails\LookupEmailTrackingLocation;
use App\Actions\Emails\ReadMaxMindDatabase;
use App\Enums\EmailTrackingEventType;
use App\Jobs\ProcessEmailTrackingEvent;
use App\Models\Email;
use App\Models\EmailDelivery;
use App\Models\EmailTrackingEvent;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;

use function Pest\Laravel\mock;

test('tracking requests durably capture events before queued projection', function () {
    Queue::fake();

    $email = Email::factory()->create();
    $delivery = EmailDelivery::factory()->for($email)->create();
    $link = $email->links()->create([
        'url' => 'https://example.com/story',
        'url_hash' => hash('sha256', 'https://example.com/story'),
        'position' => 0,
    ]);

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
        ->withHeader('User-Agent', 'Maildun tracking test')
        ->get(URL::signedRoute('emails.track.open', ['delivery' => $delivery]))
        ->assertOk();

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
        ->withHeader('User-Agent', 'Maildun tracking test')
        ->get(URL::signedRoute('emails.track.click', ['delivery' => $delivery, 'link' => $link]))
        ->assertRedirect('https://example.com/story');

    $events = EmailTrackingEvent::query()->orderBy('id')->get();

    expect($events)->toHaveCount(2)
        ->and($events[0]->type)->toBe(EmailTrackingEventType::Open)
        ->and($events[0]->email_link_id)->toBeNull()
        ->and($events[1]->type)->toBe(EmailTrackingEventType::Click)
        ->and($events[1]->email_link_id)->toBe($link->id)
        ->and($events[0]->processed_at)->toBeNull()
        ->and($events[0]->last_dispatched_at)->not->toBeNull()
        ->and($events[0]->user_agent)->toBe('Maildun tracking test')
        ->and($events[0]->ip_hash)->toHaveLength(64)
        ->and($events[0]->ip_hash)->not->toContain('203.0.113.10')
        ->and($delivery->fresh()->opens_count)->toBe(0)
        ->and($delivery->fresh()->clicks_count)->toBe(0);

    Queue::assertPushed(ProcessEmailTrackingEvent::class, 2);
    Queue::assertPushed(
        ProcessEmailTrackingEvent::class,
        fn (ProcessEmailTrackingEvent $job): bool => $job->queue === config('delivery.queues.tracking'),
    );
});

test('a tracking event is projected exactly once across duplicate job execution', function () {
    $email = Email::factory()->create();
    $delivery = EmailDelivery::factory()->for($email)->create();
    $event = EmailTrackingEvent::factory()->for($delivery, 'delivery')->create([
        'occurred_at' => now()->subMinute(),
    ]);

    $job = new ProcessEmailTrackingEvent($event->id);
    app()->call([$job, 'handle']);
    app()->call([$job, 'handle']);

    expect($event->fresh()->processed_at)->not->toBeNull()
        ->and($event->fresh()->processing_attempts)->toBe(1)
        ->and($delivery->fresh()->opens_count)->toBe(1)
        ->and($email->trackingAggregate()->value('total_opens_count'))->toBe(1)
        ->and($email->trackingAggregate()->value('unique_opens_count'))->toBe(1);
});

test('out of order jobs preserve event occurrence timestamps', function () {
    $email = Email::factory()->create();
    $delivery = EmailDelivery::factory()->for($email)->create();
    $firstAt = now()->subMinutes(10)->startOfSecond();
    $lastAt = now()->subMinute()->startOfSecond();
    $firstEvent = EmailTrackingEvent::factory()->for($delivery, 'delivery')->create([
        'occurred_at' => $firstAt,
    ]);
    $lastEvent = EmailTrackingEvent::factory()->for($delivery, 'delivery')->create([
        'occurred_at' => $lastAt,
    ]);

    app()->call([(new ProcessEmailTrackingEvent($lastEvent->id)), 'handle']);
    app()->call([(new ProcessEmailTrackingEvent($firstEvent->id)), 'handle']);

    $fresh = $delivery->fresh();
    $aggregate = $email->trackingAggregate()->firstOrFail();

    expect($fresh->opens_count)->toBe(2)
        ->and($fresh->first_opened_at?->equalTo($firstAt))->toBeTrue()
        ->and($fresh->last_opened_at?->equalTo($lastAt))->toBeTrue()
        ->and($aggregate->first_opened_at?->equalTo($firstAt))->toBeTrue()
        ->and($aggregate->last_opened_at?->equalTo($lastAt))->toBeTrue();
});

test('a malformed event rolls back projections and remains retryable', function () {
    $delivery = EmailDelivery::factory()->create();
    $event = EmailTrackingEvent::factory()->for($delivery, 'delivery')->create([
        'type' => EmailTrackingEventType::Click,
        'email_link_id' => null,
    ]);

    expect(fn () => app()->call([(new ProcessEmailTrackingEvent($event->id)), 'handle']))
        ->toThrow(LogicException::class);

    expect($event->fresh()->processed_at)->toBeNull()
        ->and($event->fresh()->processing_attempts)->toBe(1)
        ->and($event->fresh()->last_error)->toContain('must reference a link')
        ->and($delivery->fresh()->opens_count)->toBe(0)
        ->and($delivery->fresh()->clicks_count)->toBe(0);
});

test('the recovery command dispatches only missing or stale pending events', function () {
    Queue::fake();

    $stale = EmailTrackingEvent::factory()->create([
        'last_dispatched_at' => now()->subMinutes(10),
    ]);
    $missing = EmailTrackingEvent::factory()->create();
    EmailTrackingEvent::factory()->create([
        'last_dispatched_at' => now(),
    ]);
    EmailTrackingEvent::factory()->create([
        'processed_at' => now(),
        'last_dispatched_at' => now()->subMinutes(10),
    ]);

    $this->artisan('emails:dispatch-tracking-events')->assertSuccessful();

    Queue::assertPushed(ProcessEmailTrackingEvent::class, 2);
    Queue::assertPushed(
        ProcessEmailTrackingEvent::class,
        fn (ProcessEmailTrackingEvent $job): bool => $job->eventId === $stale->id,
    );
    Queue::assertPushed(
        ProcessEmailTrackingEvent::class,
        fn (ProcessEmailTrackingEvent $job): bool => $job->eventId === $missing->id,
    );

    expect($stale->fresh()->last_dispatched_at?->greaterThan(now()->subMinute()))->toBeTrue();
    expect($missing->fresh()->last_dispatched_at)->not->toBeNull();
});

test('tracking event payloads are immutable after capture', function () {
    $event = EmailTrackingEvent::factory()->create();

    expect(fn () => $event->update(['occurred_at' => now()->addMinute()]))
        ->toThrow(LogicException::class);
});

test('local city and ASN records are normalized for event capture', function () {
    config()->set('tracking.geolocation.enabled', true);
    config()->set('tracking.geolocation.city_database', '/tmp/city.mmdb');
    config()->set('tracking.geolocation.asn_database', '/tmp/asn.mmdb');

    $database = mock(ReadMaxMindDatabase::class);
    $database->shouldReceive('handle')
        ->once()
        ->with('/tmp/city.mmdb', '8.8.8.8')
        ->andReturn([
            'country' => ['iso_code' => 'us'],
            'subdivisions' => [[
                'iso_code' => 'CA',
                'names' => ['en' => 'California'],
            ]],
            'city' => ['names' => ['en' => 'Mountain View']],
            'location' => ['latitude' => 37.4229, 'longitude' => -122.085],
        ]);
    $database->shouldReceive('handle')
        ->once()
        ->with('/tmp/asn.mmdb', '8.8.8.8')
        ->andReturn([
            'autonomous_system_number' => 15169,
            'autonomous_system_organization' => 'Google LLC',
        ]);

    $location = (new LookupEmailTrackingLocation($database))->handle('8.8.8.8');

    expect($location)->toMatchArray([
        'country_code' => 'US',
        'subdivision_code' => 'CA',
        'subdivision_name' => 'California',
        'city_name' => 'Mountain View',
        'latitude' => 37.4229,
        'longitude' => -122.085,
        'network_asn' => 15169,
        'network_name' => 'Google LLC',
        'geolocation_source' => 'db-ip-lite',
    ])->and($location['geolocated_at'])->not->toBeNull();
});

test('captured events retain derived geography without retaining the raw IP', function () {
    Queue::fake();

    $location = mock(LookupEmailTrackingLocation::class);
    $location->shouldReceive('handle')
        ->once()
        ->with('8.8.8.8')
        ->andReturn([
            'country_code' => 'US',
            'subdivision_code' => 'CA',
            'subdivision_name' => 'California',
            'city_name' => 'Mountain View',
            'latitude' => 37.4229,
            'longitude' => -122.085,
            'network_asn' => 15169,
            'network_name' => 'Google LLC',
            'geolocation_source' => 'db-ip-lite',
            'geolocated_at' => now(),
        ]);
    app()->instance(LookupEmailTrackingLocation::class, $location);

    $delivery = EmailDelivery::factory()->create();

    $this->withServerVariables(['REMOTE_ADDR' => '8.8.8.8'])
        ->get(URL::signedRoute('emails.track.open', ['delivery' => $delivery]))
        ->assertOk();

    $event = EmailTrackingEvent::query()->firstOrFail();

    expect($event->country_code)->toBe('US')
        ->and($event->city_name)->toBe('Mountain View')
        ->and($event->latitude)->toBe(37.4229)
        ->and($event->longitude)->toBe(-122.085)
        ->and($event->network_asn)->toBe(15169)
        ->and($event->network_name)->toBe('Google LLC')
        ->and($event->ip_hash)->not->toContain('8.8.8.8');
});

test('missing MMDB files leave enrichment empty without blocking tracking', function () {
    config()->set('tracking.geolocation.enabled', true);
    config()->set('tracking.geolocation.city_database', '/missing/city.mmdb');
    config()->set('tracking.geolocation.asn_database', '/missing/asn.mmdb');

    $location = (new LookupEmailTrackingLocation(new ReadMaxMindDatabase))->handle('8.8.8.8');

    expect($location)->toBe([]);
});
