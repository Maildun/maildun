<?php

namespace App\Enums;

/**
 * Which share of the instance's send rate a message draws its slot from.
 *
 * Transactional mail (receipts, password resets, API sends) gets a reserved
 * share so a large campaign or automation cannot starve it; campaigns and
 * automations share the rest. The two shares sum to the configured rate, so
 * together they never exceed the provider's quota. Each value is the name of
 * the queue rate limiter registered for that lane.
 */
enum EmailSendLane: string
{
    case Bulk = 'email-delivery';
    case Transactional = 'email-delivery-transactional';

    /**
     * The named limiter a send in this lane waits on. A rate too small to
     * split (or a zero share) leaves transactional mail sharing the bulk
     * limiter, as every send did before the rate was split.
     */
    public function limiterName(): string
    {
        return self::reservedTransactionalPerSecond() > 0 ? $this->value : self::Bulk->value;
    }

    /**
     * Messages per second this lane may hand to the transport; 0 when the
     * instance has no send rate configured.
     */
    public function perSecond(): int
    {
        $total = max(0, (int) config('delivery.rate_limit.per_second'));
        $reserved = self::reservedTransactionalPerSecond();

        return match ($this) {
            self::Bulk => $total - $reserved,
            self::Transactional => $reserved > 0 ? $reserved : $total,
        };
    }

    /**
     * The configured percentage of the rate, rounded down, but at least one
     * message per second, and never so much that bulk mail is left with none.
     */
    private static function reservedTransactionalPerSecond(): int
    {
        $total = (int) config('delivery.rate_limit.per_second');
        $share = min(100, (int) config('delivery.rate_limit.transactional_share'));

        if ($total < 2 || $share <= 0) {
            return 0;
        }

        return min($total - 1, max(1, intdiv($total * $share, 100)));
    }
}
