<?php

namespace App\Enums;

enum EmailDeliveryStatus: string
{
    case Queued = 'queued';
    case Sending = 'sending';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Delayed = 'delayed';
    case Bounced = 'bounced';
    case Complained = 'complained';
    case Rejected = 'rejected';
    case Failed = 'failed';

    /**
     * Transient send problems that can be queued again. Permanent bounces and
     * complaints are not retryable.
     *
     * @return list<self>
     */
    public static function retryable(): array
    {
        return [self::Failed, self::Rejected, self::Delayed];
    }

    public function isRetryable(): bool
    {
        return in_array($this, self::retryable(), true);
    }
}
