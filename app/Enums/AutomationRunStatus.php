<?php

namespace App\Enums;

enum AutomationRunStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Waiting = 'waiting';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    /**
     * @return list<self>
     */
    public static function open(): array
    {
        return [self::Pending, self::Running, self::Waiting];
    }

    public function isOpen(): bool
    {
        return in_array($this, self::open(), true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Running => 'Running',
            self::Waiting => 'Waiting',
            self::Completed => 'Completed',
            self::Failed => 'Failed',
            self::Cancelled => 'Cancelled',
        };
    }
}
