<?php

namespace App\Enums;

enum SubscribeFormPoweredByPosition: string
{
    case TopLeft = 'top-left';
    case TopCenter = 'top-center';
    case TopRight = 'top-right';
    case BottomLeft = 'bottom-left';
    case BottomCenter = 'bottom-center';
    case BottomRight = 'bottom-right';

    public function label(): string
    {
        return match ($this) {
            self::TopLeft => 'Above · Left',
            self::TopCenter => 'Above · Center',
            self::TopRight => 'Above · Right',
            self::BottomLeft => 'Below · Left',
            self::BottomCenter => 'Below · Center',
            self::BottomRight => 'Below · Right',
        };
    }
}
