<?php

namespace App\Enums;

enum TeamBrandColor: string
{
    case Blue = 'blue';
    case Indigo = 'indigo';
    case Violet = 'violet';
    case Purple = 'purple';
    case Fuchsia = 'fuchsia';
    case Pink = 'pink';
    case Rose = 'rose';
    case Red = 'red';
    case Orange = 'orange';
    case Amber = 'amber';
    case Lime = 'lime';
    case Emerald = 'emerald';
    case Teal = 'teal';
    case Cyan = 'cyan';
    case Neutral = 'neutral';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $color) => [
            'value' => $color->value,
            'label' => $color->label(),
        ], self::cases());
    }
}
