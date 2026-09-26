<?php

namespace App\Enums;

enum EmailStatus: string
{
    case Draft = 'draft';
    case Queued = 'queued';
    case Sending = 'sending';
    case Sent = 'sent';
    case PartiallyFailed = 'partially_failed';
    case Failed = 'failed';
    /** Stopped by a person while sending; recipients not yet sent to were cancelled. */
    case Stopped = 'stopped';

    /**
     * Statuses where a send is still in flight.
     *
     * @return list<self>
     */
    public static function active(): array
    {
        return [self::Queued, self::Sending];
    }

    public function isActive(): bool
    {
        return in_array($this, self::active(), true);
    }
}
