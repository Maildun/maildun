<?php

namespace App\Concerns;

use App\Services\TeamMailer;
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
     * Three genuine transport failures still fail the delivery. This is counted
     * separately from attempts, so waiting for a rate-limit slot never does.
     */
    public int $maxExceptions = 3;

    /** @return list<object> */
    public function middleware(): array
    {
        if (! $this->emailRateLimitIsEnabled()) {
            return [];
        }

        $middleware = config('cache.default') === 'redis'
            ? new RateLimitedWithRedis(TeamMailer::RATE_LIMITER)
            : new RateLimited(TeamMailer::RATE_LIMITER);

        return [$middleware->releaseAfter(max(1, (int) config('delivery.rate_limit.release_after')))];
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
        return $this->emailRateLimitIsEnabled() ? now()->addHours(6) : null;
    }

    private function emailRateLimitIsEnabled(): bool
    {
        return (int) config('delivery.rate_limit.per_second') > 0;
    }
}
