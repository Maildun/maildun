<?php

namespace App\Enums;

enum SubscriberSource: string
{
    case Manual = 'manual';
    case Form = 'form';
    case Api = 'api';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Manual',
            self::Form => 'Form',
            self::Api => 'API',
        };
    }
}
