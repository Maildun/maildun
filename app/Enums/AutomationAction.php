<?php

namespace App\Enums;

enum AutomationAction: string
{
    case SendEmail = 'send_email';
    case AddTag = 'add_tag';
    case RemoveTag = 'remove_tag';

    public function label(): string
    {
        return match ($this) {
            self::SendEmail => 'Send email',
            self::AddTag => 'Add tag',
            self::RemoveTag => 'Remove tag',
        };
    }
}
