<?php

namespace App\Enums;

enum SubscribeFormLogoShape: string
{
    case Default = 'default';
    case Square = 'square';
    case RoundedLg = 'rounded-lg';
    case RoundedXl = 'rounded-xl';
    case RoundedFull = 'rounded-full';

    public function label(): string
    {
        return match ($this) {
            self::Default => 'Original',
            self::Square => 'Square',
            self::RoundedLg => 'Rounded lg',
            self::RoundedXl => 'Rounded xl',
            self::RoundedFull => 'Full rounded',
        };
    }
}
