<?php

namespace App\Enums;

enum TeamBrandInputStyle: string
{
    case Default = 'default';
    case Soft = 'soft';
    case Underline = 'underline';

    public function label(): string
    {
        return match ($this) {
            self::Default => 'Default',
            self::Soft => 'Soft / Filled',
            self::Underline => 'Underline',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $style) => [
            'value' => $style->value,
            'label' => $style->label(),
        ], self::cases());
    }
}
