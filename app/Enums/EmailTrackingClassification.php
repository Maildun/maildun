<?php

namespace App\Enums;

enum EmailTrackingClassification: string
{
    case Human = 'human';
    case Bot = 'bot';
    case PrivacyProxy = 'privacy_proxy';
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::Human => 'Human',
            self::Bot => 'Automated',
            self::PrivacyProxy => 'Privacy proxy',
            self::Unknown => 'Unknown',
        };
    }
}
