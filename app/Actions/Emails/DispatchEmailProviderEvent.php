<?php

namespace App\Actions\Emails;

use App\Jobs\ProcessEmailProviderEvent;
use App\Models\EmailProviderEvent;
use Illuminate\Support\Str;
use Throwable;

class DispatchEmailProviderEvent
{
    /**
     * Queue a stored provider event for processing. A queue that cannot take
     * the job leaves the event pending for emails:dispatch-provider-events,
     * so the caller never has to fail a notification that is already stored.
     */
    public function handle(EmailProviderEvent $event): bool
    {
        try {
            ProcessEmailProviderEvent::dispatch($event->id);

            EmailProviderEvent::query()
                ->whereKey($event->id)
                ->whereNull('processed_at')
                ->update([
                    'last_dispatched_at' => now(),
                    'last_error' => null,
                ]);

            return true;
        } catch (Throwable $exception) {
            report($exception);

            EmailProviderEvent::query()
                ->whereKey($event->id)
                ->whereNull('processed_at')
                ->update(['last_error' => Str::limit($exception->getMessage(), 2000)]);

            return false;
        }
    }
}
