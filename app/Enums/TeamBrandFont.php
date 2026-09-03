<?php

namespace App\Enums;

enum TeamBrandFont: string
{
    case Inter = 'inter';
    case InstrumentSans = 'instrument-sans';
    case SystemSans = 'system-sans';
    case RoundedSans = 'rounded-sans';
    case HumanistSans = 'humanist-sans';
    case Serif = 'serif';
    case Georgia = 'georgia';
    case Mono = 'mono';

    public function label(): string
    {
        return match ($this) {
            self::Inter => 'Inter',
            self::InstrumentSans => 'Instrument Sans',
            self::SystemSans => 'System Sans',
            self::RoundedSans => 'Rounded Sans',
            self::HumanistSans => 'Humanist Sans',
            self::Serif => 'Serif',
            self::Georgia => 'Georgia',
            self::Mono => 'Mono',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $font) => [
            'value' => $font->value,
            'label' => $font->label(),
        ], self::cases());
    }
}
