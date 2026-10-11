<?php

namespace Abduns\DbBackup\Enums;

enum BackupStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Succeeded = 'succeeded';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Queued',
            self::Running => 'Running',
            self::Succeeded => 'Succeeded',
            self::Failed => 'Failed',
        };
    }

    public function isFinished(): bool
    {
        return $this === self::Succeeded || $this === self::Failed;
    }
}
