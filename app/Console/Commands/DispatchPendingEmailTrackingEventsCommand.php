<?php

namespace App\Console\Commands;

use App\Actions\Emails\DispatchEmailTrackingEvent;
use App\Models\EmailTrackingEvent;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

#[Signature('emails:dispatch-tracking-events {--limit=1000 : Maximum pending events to dispatch}')]
#[Description('Dispatch durable email tracking events that have no active queue job')]
class DispatchPendingEmailTrackingEventsCommand extends Command
{
    public function handle(DispatchEmailTrackingEvent $dispatchEvent): int
    {
        $limit = min(10_000, max(1, (int) $this->option('limit')));
        $staleBefore = now()->subMinutes(
            max(1, (int) config('delivery.recovery.tracking_redispatch_after_minutes')),
        );
        $events = EmailTrackingEvent::query()
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
            $this->info(__(':count tracking event(s) dispatched.', ['count' => $dispatched]));
        }

        return self::SUCCESS;
    }
}
