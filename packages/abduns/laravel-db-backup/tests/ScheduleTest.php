<?php

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;

/**
 * @return array<string, Event>
 */
function scheduledBackupEvents(): array
{
    return collect(app(Schedule::class)->events())
        ->filter(fn (Event $event): bool => str_contains((string) $event->command, 'db-backup:'))
        ->mapWithKeys(fn (Event $event): array => [trim(str((string) $event->command)->after('artisan')->value(), " '\"") => $event])
        ->all();
}

test('nothing is scheduled until the schedule is enabled', function () {
    expect(scheduledBackupEvents())->toBe([]);
});

test('the backup, prune and monitor run daily at the configured time and timezone', function () {
    config([
        'db-backup.schedule.enabled' => true,
        'db-backup.schedule.time' => '23:30',
        'db-backup.schedule.monitor_time' => '07:15',
        'db-backup.schedule.timezone' => 'Asia/Jakarta',
    ]);

    $events = scheduledBackupEvents();

    expect(array_keys($events))->toBe(['db-backup:run', 'db-backup:prune', 'db-backup:monitor'])
        ->and($events['db-backup:run']->expression)->toBe('30 23 * * *')
        ->and($events['db-backup:prune']->expression)->toBe('30 0 * * *')
        ->and($events['db-backup:monitor']->expression)->toBe('15 7 * * *')
        ->and(collect($events)->every(fn (Event $event): bool => $event->timezone === 'Asia/Jakarta'))->toBeTrue();
});

test('the schedule falls back to the app timezone', function () {
    config(['db-backup.schedule.enabled' => true, 'db-backup.schedule.timezone' => null, 'app.timezone' => 'Europe/Amsterdam']);

    expect(scheduledBackupEvents()['db-backup:run']->timezone)->toBe('Europe/Amsterdam');
});
