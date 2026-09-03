<?php

namespace App\Enums;

enum AutomationTrigger: string
{
    case Subscribed = 'subscriber.subscribed';
    case Unsubscribed = 'subscriber.unsubscribed';
    case Resubscribed = 'subscriber.resubscribed';
    case Tagged = 'subscriber.tagged';
    case Api = 'api';

    public function label(): string
    {
        return match ($this) {
            self::Subscribed => 'Someone subscribes',
            self::Unsubscribed => 'Someone unsubscribes',
            self::Resubscribed => 'Someone resubscribes',
            self::Tagged => 'A tag is added',
            self::Api => 'API call',
        };
    }
}
