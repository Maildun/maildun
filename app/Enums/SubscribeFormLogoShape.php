<?php

namespace App\Enums;

enum SubscribeFormLogoShape: string
{
    case Default = 'default';
    case Square = 'square';

    public function label(): string
    {
        return match ($this) {
            self::Default => 'Default',
            self::Square => 'Square',
        };
    }
}
