<?php

namespace App\Actions\Emails;

use App\Jobs\ProcessEmailTrackingEvent;
use App\Models\EmailTrackingEvent;
use Illuminate\Support\Str;
use Throwable;

class DispatchEmailTrackingEvent
{
    public function handle(EmailTrackingEvent $event): bool
    {
        try {
            ProcessEmailTrackingEvent::dispatch($event->id);

            EmailTrackingEvent::query()
                ->whereKey($event->id)
                ->whereNull('processed_at')
                ->update([
                    'last_dispatched_at' => now(),
                    'last_error' => null,
                ]);

            return true;
        } catch (Throwable $exception) {
            report($exception);

            EmailTrackingEvent::query()
                ->whereKey($event->id)
                ->whereNull('processed_at')
                ->update(['last_error' => Str::limit($exception->getMessage(), 2000)]);

            return false;
        }
    }
}
