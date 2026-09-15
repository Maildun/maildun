<?php

namespace App\Enums;

enum SubscribeFormHeaderSpacing: string
{
    case Compact = 'compact';
    case Default = 'default';
    case Relaxed = 'relaxed';
    case Spacious = 'spacious';

    public function label(): string
    {
        return match ($this) {
            self::Compact => 'Compact',
            self::Default => 'Default',
            self::Relaxed => 'Relaxed',
            self::Spacious => 'Spacious',
        };
    }
}
