<?php

namespace App\Enums;

enum EmailAddressHealthReason: string
{
    case Delivered = 'delivered';
    case SendFailure = 'send_failure';
    case TransientBounce = 'transient_bounce';
    case PermanentBounce = 'permanent_bounce';
    case Complaint = 'complaint';

    public function label(): string
    {
        return match ($this) {
            self::Delivered => 'Previously delivered',
            self::SendFailure => 'Send failed',
            self::TransientBounce => 'Temporary bounce',
            self::PermanentBounce => 'Permanent bounce',
            self::Complaint => 'Spam complaint',
        };
    }
}
