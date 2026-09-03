<?php

use App\Models\TeamInvitation;
use Illuminate\Support\Facades\Schedule;

Schedule::call(function () {
    TeamInvitation::query()
        ->whereNotNull('expires_at')
        ->where('expires_at', '<', now())
        ->delete();
})->daily()->description('Delete expired team invitations');

Schedule::command('segments:sync')->everyFiveMinutes();

Schedule::command('horizon:snapshot')->everyFiveMinutes();

Schedule::command('passport:purge --hours=168')->daily()->withoutOverlapping();

// Reads Maildun's public release manifest and only writes the cached result.
Schedule::command('app:check-for-updates')->daily()->withoutOverlapping()->onOneServer();

// The database event is durable even if Redis is flushed or unavailable.
Schedule::command('emails:dispatch-tracking-events')->everyMinute()->withoutOverlapping();

// Detailed events expire; durable campaign aggregates remain available.
Schedule::command('emails:prune-tracking')->daily()->withoutOverlapping();

// Recovers delayed runs whose queued job was lost. withoutOverlapping so a slow
// sweep cannot stack a second copy on top of itself.
Schedule::command('automations:resume')->everyFiveMinutes()->withoutOverlapping();

// Same problem on the send side: re-queues deliveries whose job was lost and
// closes campaigns whose batch disappeared mid-send.
Schedule::command('emails:resume')->everyFiveMinutes()->withoutOverlapping();
