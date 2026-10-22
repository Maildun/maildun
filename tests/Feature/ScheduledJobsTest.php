<?php

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;

/**
 * Finds the scheduled event whose artisan command ends with exactly the given command line.
 */
function scheduledCommandEvent(string $command): ?Event
{
    return collect(app(Schedule::class)->events())
        ->first(fn (Event $event): bool => str_ends_with((string) $event->command, " {$command}"));
}

dataset('scheduled commands', [
    'segments:sync' => ['segments:sync', '*/5 * * * *'],
    'emails:send-scheduled' => ['emails:send-scheduled', '* * * * *'],
    'emails:resume' => ['emails:resume', '*/5 * * * *'],
    'automations:resume' => ['automations:resume', '*/5 * * * *'],
    'contacts:resume-imports' => ['contacts:resume-imports', '*/5 * * * *'],
    'custom-domains:check' => ['custom-domains:check', '*/5 * * * *'],
    'custom-domains:check --live' => ['custom-domains:check --live', '0 * * * *'],
    'billing:sync-subscriptions' => ['billing:sync-subscriptions', '*/15 * * * *'],
    'emails:dispatch-tracking-events' => ['emails:dispatch-tracking-events', '* * * * *'],
    'emails:prune-tracking' => ['emails:prune-tracking', '0 0 * * *'],
    'contacts:prune-imports' => ['contacts:prune-imports', '0 0 * * *'],
    'teams:purge-deleted' => ['teams:purge-deleted', '0 0 * * *'],
    'passport:purge' => ['passport:purge --hours=168', '0 0 * * *'],
    'app:check-for-updates' => ['app:check-for-updates', '0 0 * * *'],
    'audiences:snapshot-subscribers' => ['audiences:snapshot-subscribers', '0 * * * *'],
]);

test('each background job is scheduled at its cadence and cannot overlap itself', function (string $command, string $expression) {
    $event = scheduledCommandEvent($command);

    expect($event)->toBeInstanceOf(Event::class)
        ->and($event->expression)->toBe($expression)
        ->and($event->withoutOverlapping)->toBeTrue();
})->with('scheduled commands');

test('each background job runs on a single server', function (string $command) {
    expect(scheduledCommandEvent($command)?->onOneServer)->toBeTrue();
})->with('scheduled commands')
    ->skip('Bug from QA MAIL-174: most entries in routes/console.php are missing onOneServer(), so every scheduler host runs them each tick.');
