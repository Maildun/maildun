<?php

namespace App\Enums;

enum AutomationCondition: string
{
    case HasTag = 'has_tag';
    case IsSubscribed = 'is_subscribed';
    case Source = 'source';
    case Language = 'language';

    public function label(): string
    {
        return match ($this) {
            self::HasTag => 'Has tag',
            self::IsSubscribed => 'Is subscribed',
            self::Source => 'Source',
            self::Language => 'Language',
        };
    }
}
