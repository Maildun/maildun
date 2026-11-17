<?php

namespace App\Console\Commands;

use App\Actions\Emails\DispatchEmailProviderEvent;
use App\Models\EmailProviderEvent;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

#[Signature('emails:dispatch-provider-events {--limit=1000 : Maximum pending events to dispatch} {--retry-failed : Give events that were marked failed another round of attempts}')]
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
        $this->retireOrReviveFailingEvents();

        $events = EmailProviderEvent::query()
            ->whereNull('processed_at')
            ->whereNull('failed_at')
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

    /**
     * An event that has used up its processing attempts is marked failed and
     * no longer swept, so one that can never succeed is not re-queued forever
     * and does not hold back the lost events behind it. --retry-failed gives
     * every failed event a fresh round once the cause is fixed.
     */
    private function retireOrReviveFailingEvents(): void
    {
        if ($this->option('retry-failed')) {
            $revived = EmailProviderEvent::query()
                ->whereNull('processed_at')
                ->whereNotNull('failed_at')
                ->update(['failed_at' => null, 'processing_attempts' => 0, 'last_dispatched_at' => null]);

            if ($revived > 0) {
                $this->info(__(':count failed provider feedback event(s) queued for another try.', ['count' => $revived]));
            }
        }

        $failed = EmailProviderEvent::query()
            ->whereNull('processed_at')
            ->whereNull('failed_at')
            ->where('processing_attempts', '>=', max(1, (int) config('delivery.recovery.event_max_processing_attempts')))
            ->update(['failed_at' => now()]);

        if ($failed > 0) {
            $this->warn(__(':count provider feedback event(s) failed too often and will not be retried.', ['count' => $failed]));
        }
    }
}
