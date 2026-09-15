<?php

namespace App\Enums;

enum SubscribeFormLogoPosition: string
{
    case Left = 'left';
    case Center = 'center';
    case Right = 'right';

    public function label(): string
    {
        return match ($this) {
            self::Left => 'Left',
            self::Center => 'Center',
            self::Right => 'Right',
        };
    }
}
