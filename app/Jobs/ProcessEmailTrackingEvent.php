<?php

namespace App\Jobs;

use App\Actions\Emails\RecordEmailClick;
use App\Actions\Emails\RecordEmailOpen;
use App\Actions\Emails\RecordEmailTrackingInsights;
use App\Enums\EmailTrackingEventType;
use App\Models\EmailDelivery;
use App\Models\EmailLink;
use App\Models\EmailTrackingEvent;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;
use Throwable;

class ProcessEmailTrackingEvent implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 20;

    public int $uniqueFor = 900;

    /** @var list<int> */
    public array $backoff = [1, 5, 30, 120];

    public function __construct(public int $eventId)
    {
        $this->onQueue((string) config('delivery.queues.tracking'));
    }

    public function uniqueId(): string
    {
        return 'email-tracking-event:'.$this->eventId;
    }

    public function handle(
        RecordEmailOpen $recordOpen,
        RecordEmailClick $recordClick,
        RecordEmailTrackingInsights $recordInsights,
    ): void {
        try {
            DB::transaction(function () use ($recordOpen, $recordClick, $recordInsights): void {
                $event = EmailTrackingEvent::query()
                    ->whereKey($this->eventId)
                    ->lockForUpdate()
                    ->first();

                if ($event === null || $event->processed_at !== null) {
                    return;
                }

                $delivery = EmailDelivery::query()->findOrFail($event->email_delivery_id);

                if ($event->type === EmailTrackingEventType::Open) {
                    $recordInsights->handle(
                        event: $event,
                        delivery: $delivery,
                        countedAsOpen: $recordOpen->handle($delivery, $event->occurred_at),
                        countedAsClick: false,
                    );
                } else {
                    $link = $this->clickLink($event, $delivery);
                    $projection = $recordClick->handle($delivery, $link, $event->occurred_at);
                    $recordInsights->handle(
                        event: $event,
                        delivery: $delivery,
                        countedAsOpen: $projection['open'],
                        countedAsClick: $projection['click'],
                    );
                }

                $event->forceFill([
                    'processed_at' => now(),
                    'processing_attempts' => $event->processing_attempts + 1,
                    'last_error' => null,
                ])->save();
            }, attempts: 3);
        } catch (Throwable $exception) {
            EmailTrackingEvent::query()
                ->whereKey($this->eventId)
                ->whereNull('processed_at')
                ->increment('processing_attempts', 1, [
                    'last_error' => Str::limit($exception->getMessage(), 2000),
                ]);

            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        EmailTrackingEvent::query()
            ->whereKey($this->eventId)
            ->whereNull('processed_at')
            ->update([
                'last_error' => Str::limit(
                    $exception?->getMessage() ?: 'Tracking event processing failed.',
                    2000,
                ),
            ]);
    }

    /** @return list<string> */
    public function tags(): array
    {
        return ['email-tracking', 'tracking-event:'.$this->eventId];
    }

    private function clickLink(EmailTrackingEvent $event, EmailDelivery $delivery): EmailLink
    {
        if ($event->email_link_id === null) {
            throw new LogicException('Click tracking events must reference a link.');
        }

        $link = EmailLink::query()->findOrFail($event->email_link_id);

        if ($link->email_id !== $delivery->email_id) {
            throw new LogicException('The tracked link does not belong to the delivery campaign.');
        }

        return $link;
    }
}
