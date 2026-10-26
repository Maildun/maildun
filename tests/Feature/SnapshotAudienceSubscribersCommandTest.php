<?php

use App\Models\Audience;
use App\Models\AudienceSubscriberSnapshot;
use App\Models\Subscriber;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;

test('the first run backfills every finished day of the chart window from current members', function () {
    $this->travelTo('2026-08-12 12:00:00');

    $audience = Audience::factory()->create();
    Subscriber::factory()->for($audience)->joinedAt(now()->subDays(11))->create();
    Subscriber::factory()->for($audience)->joinedAt(now()->subDays(11))
        ->unsubscribed(now()->subDays(7))
        ->create();

    $this->artisan('audiences:snapshot-subscribers')->assertSuccessful();

    $snapshots = AudienceSubscriberSnapshot::query()
        ->whereBelongsTo($audience)
        ->get()
        ->keyBy(fn (AudienceSubscriberSnapshot $snapshot): string => $snapshot->day->toDateString());

    expect($snapshots)->toHaveCount(55)
        ->and($snapshots->keys()->min())->toBe('2026-06-18')
        ->and($snapshots->keys()->max())->toBe('2026-08-11')
        ->and($snapshots->get('2026-07-31')->only(['total', 'unsubscribed', 'pending', 'new']))
        ->toBe(['total' => 0, 'unsubscribed' => 0, 'pending' => 0, 'new' => 0])
        ->and($snapshots->get('2026-08-01')->only(['total', 'unsubscribed', 'pending', 'new']))
        ->toBe(['total' => 2, 'unsubscribed' => 0, 'pending' => 0, 'new' => 2])
        ->and($snapshots->get('2026-08-05')->only(['total', 'unsubscribed', 'pending', 'new']))
        ->toBe(['total' => 2, 'unsubscribed' => 1, 'pending' => 0, 'new' => 0]);
});

test('days that already have a snapshot are never rewritten', function () {
    $this->travelTo('2026-08-12 12:00:00');

    $audience = Audience::factory()->create();
    Subscriber::factory()->for($audience)->joinedAt(now()->subDays(11))->create();
    $recorded = AudienceSubscriberSnapshot::factory()->for($audience)->create([
        'day' => '2026-08-11',
        'total' => 5,
        'new' => 1,
    ]);

    $this->artisan('audiences:snapshot-subscribers')->assertSuccessful();

    expect($recorded->fresh()->only(['total', 'unsubscribed', 'pending', 'new']))
        ->toBe(['total' => 5, 'unsubscribed' => 0, 'pending' => 0, 'new' => 1])
        ->and(AudienceSubscriberSnapshot::query()->whereBelongsTo($audience)->count())->toBe(55);
});

test('the subscriber snapshot is scheduled so runs cannot overlap', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn (Event $event): bool => str_contains((string) $event->command, 'audiences:snapshot-subscribers'));

    expect($event)->toBeInstanceOf(Event::class)
        ->and($event->withoutOverlapping)->toBeTrue()
        ->and($event->onOneServer)->toBeTrue();
});
