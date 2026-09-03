<?php

namespace App\Enums;

enum SubscribeFormStyle: string
{
    case Card = 'card';
    case Split = 'split';
    case Minimal = 'minimal';
    case Cover = 'cover';

    public function label(): string
    {
        return match ($this) {
            self::Card => 'Classic',
            self::Split => 'Split',
            self::Minimal => 'Minimal',
            self::Cover => 'Cover',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Card => 'Centered card on a muted page.',
            self::Split => 'Form beside a full-height image panel.',
            self::Minimal => 'Just the fields, centered, no card.',
            self::Cover => 'Form card over a full-bleed image.',
        };
    }

    /**
     * @return list<array{value: string, label: string, description: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $style) => [
            'value' => $style->value,
            'label' => $style->label(),
            'description' => $style->description(),
        ], self::cases());
    }
}
