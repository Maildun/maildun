<?php

namespace App\Concerns;

use App\Enums\EmailSendLane;
use DateTimeInterface;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\RateLimitedWithRedis;

/**
 * Holds outbound sends to the instance's configured send rate.
 *
 * Every provider enforces a quota and rejects the overflow, so without this a
 * large campaign spends real recipients on throttling errors.
 */
trait ThrottlesEmailDelivery
{
    /**
     * How long a throttled job may keep waiting for a send slot.
     */
    public const int RETRY_WINDOW_HOURS = 6;

    /**
     * Three genuine transport failures still fail the delivery. This is counted
     * separately from attempts, so waiting for a rate-limit slot never does.
     */
    public int $maxExceptions = 3;

    /**
     * Checks that can drop the job run before the throttle, so a job that would
     * send nothing never spends one of the instance's send slots.
     *
     * @return list<object>
     */
    public function middleware(): array
    {
        return [...$this->middlewareBeforeSendSlot(), ...$this->sendSlotMiddleware()];
    }

    /**
     * A throttled job is released back onto the queue each time it misses a
     * slot, and every release consumes an attempt. Bound those by time instead,
     * so a busy queue cannot exhaust $tries and fail a recipient nothing is
     * actually wrong with. Without a rate limit there is nothing to wait for,
     * so the plain $tries budget stands.
     */
    public function retryUntil(): ?DateTimeInterface
    {
        return $this->emailRateLimitIsEnabled() ? now()->addHours(self::RETRY_WINDOW_HOURS) : null;
    }

    /**
     * Middleware that runs before a send slot is taken. A job overrides this
     * to skip work that can no longer send, such as a stopped campaign's.
     *
     * @return list<object>
     */
    protected function middlewareBeforeSendSlot(): array
    {
        return [];
    }

    /**
     * Which share of the send rate this job draws from. Campaign and automation
     * mail share the bulk lane; transactional mail overrides this.
     */
    protected function sendLane(): EmailSendLane
    {
        return EmailSendLane::Bulk;
    }

    /** @return list<object> */
    private function sendSlotMiddleware(): array
    {
        if (! $this->emailRateLimitIsEnabled()) {
            return [];
        }

        $limiterName = $this->sendLane()->limiterName();
        $middleware = config('cache.default') === 'redis'
            ? new RateLimitedWithRedis($limiterName)
            : new RateLimited($limiterName);

        return [$middleware->releaseAfter(max(1, (int) config('delivery.rate_limit.release_after')))];
    }

    private function emailRateLimitIsEnabled(): bool
    {
        return (int) config('delivery.rate_limit.per_second') > 0;
    }
}
