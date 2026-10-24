<?php

namespace Abduns\DbBackup\Events;

use Abduns\DbBackup\Models\DatabaseBackup;
use Illuminate\Queue\SerializesModels;

class BackupFailed
{
    use SerializesModels;

    public function __construct(public DatabaseBackup $backup) {}
}
