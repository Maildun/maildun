<?php

namespace App\Enums;

enum AutomationDelayUnit: string
{
    case Minutes = 'minutes';
    case Hours = 'hours';
    case Days = 'days';

    public function label(): string
    {
        return match ($this) {
            self::Minutes => 'Minutes',
            self::Hours => 'Hours',
            self::Days => 'Days',
        };
    }
}
