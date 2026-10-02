<?php

namespace App\Enums;

enum SubscriberLanguage: string
{
    case Arabic = 'ar';
    case Chinese = 'zh';
    case Dutch = 'nl';
    case English = 'en';
    case French = 'fr';
    case German = 'de';
    case Hindi = 'hi';
    case Indonesian = 'id';
    case Italian = 'it';
    case Japanese = 'ja';
    case Korean = 'ko';
    case Malay = 'ms';
    case Portuguese = 'pt';
    case Russian = 'ru';
    case Spanish = 'es';
    case Thai = 'th';
    case Turkish = 'tr';
    case Vietnamese = 'vi';

    public function label(): string
    {
        return match ($this) {
            self::Arabic => 'Arabic',
            self::Chinese => 'Chinese',
            self::Dutch => 'Dutch',
            self::English => 'English',
            self::French => 'French',
            self::German => 'German',
            self::Hindi => 'Hindi',
            self::Indonesian => 'Indonesian',
            self::Italian => 'Italian',
            self::Japanese => 'Japanese',
            self::Korean => 'Korean',
            self::Malay => 'Malay',
            self::Portuguese => 'Portuguese',
            self::Russian => 'Russian',
            self::Spanish => 'Spanish',
            self::Thai => 'Thai',
            self::Turkish => 'Turkish',
            self::Vietnamese => 'Vietnamese',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $language): array => [
            'value' => $language->value,
            'label' => $language->label(),
        ], self::cases());
    }
}
