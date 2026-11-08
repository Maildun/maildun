<?php

namespace App\Console\Commands;

use App\Actions\Emails\DispatchEmailProviderEvent;
use App\Models\EmailProviderEvent;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

#[Signature('emails:dispatch-provider-events {--limit=1000 : Maximum pending events to dispatch}')]
#[Description('Dispatch stored SES feedback events that have no active queue job')]
class DispatchPendingEmailProviderEventsCommand extends Command
{
    /**
     * SES feedback is stored before it is processed. An event whose job was
     * lost, or never queued because the queue was down, waits here; one that
     * was dispatched recently is left to the job that may still be running.
     */
    public function handle(DispatchEmailProviderEvent $dispatchEvent): int
    {
        $limit = min(10_000, max(1, (int) $this->option('limit')));
        $staleBefore = now()->subMinutes(
            max(1, (int) config('delivery.recovery.tracking_redispatch_after_minutes')),
        );
        $events = EmailProviderEvent::query()
            ->whereNull('processed_at')
            ->where(function (Builder $query) use ($staleBefore): void {
                $query->whereNull('last_dispatched_at')
                    ->orWhere('last_dispatched_at', '<=', $staleBefore);
            })
            ->orderBy('id')
            ->limit($limit)
            ->get();
        $dispatched = 0;

        foreach ($events as $event) {
            if ($dispatchEvent->handle($event)) {
                $dispatched++;
            }
        }

        if ($dispatched > 0) {
            $this->info(__(':count provider feedback event(s) dispatched.', ['count' => $dispatched]));
        }

        return self::SUCCESS;
    }
}
