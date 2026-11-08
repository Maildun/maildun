<?php

namespace App\Jobs;

use App\Actions\Emails\ProcessSesEvent;
use App\Models\EmailProviderEvent;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Throwable;

/**
 * Applies one stored SES notification. The webhook stores the event before
 * queueing this, so a failure here leaves it pending for the queue's retries
 * and then for emails:dispatch-provider-events, rather than losing a bounce or
 * complaint that SNS has already stopped redelivering.
 */
class ProcessEmailProviderEvent implements ShouldBeUnique, ShouldQueue
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
        return 'email-provider-event:'.$this->eventId;
    }

    /**
     * Execute the job.
     */
    public function handle(ProcessSesEvent $processor): void
    {
        try {
            $processor->process($this->eventId);
        } catch (Throwable $exception) {
            EmailProviderEvent::query()
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
        EmailProviderEvent::query()
            ->whereKey($this->eventId)
            ->whereNull('processed_at')
            ->update([
                'last_error' => Str::limit(
                    $exception?->getMessage() ?: 'Provider feedback processing failed.',
                    2000,
                ),
            ]);
    }

    /** @return list<string> */
    public function tags(): array
    {
        return ['ses-feedback', 'provider-event:'.$this->eventId];
    }
}
