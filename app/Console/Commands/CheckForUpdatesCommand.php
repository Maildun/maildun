<?php

namespace App\Console\Commands;

use App\Services\AppUpdateChecker;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:check-for-updates')]
#[Description('Check Maildun for an available application update')]
class CheckForUpdatesCommand extends Command
{
    /**
     * Fetch the official release manifest without making an update locally.
     */
    public function handle(AppUpdateChecker $updates): int
    {
        if (! $updates->enabled()) {
            $this->components->info('Maildun update checks are disabled.');

            return self::SUCCESS;
        }

        if (! $updates->refresh()) {
            $this->components->warn('Maildun could not check for updates. The last successful result is still available in System Check.');

            return self::SUCCESS;
        }

        $status = $updates->status();

        $this->components->twoColumnDetail('Installed version', $status['current_version']);
        $this->components->twoColumnDetail('Latest version', (string) $status['latest_version']);

        if ($status['status'] === 'unsupported') {
            $this->components->warn('This Maildun installation is below the minimum supported version.');
        } elseif ($status['status'] === 'update_available') {
            $this->components->info('A Maildun update is available.');
        } else {
            $this->components->info('Maildun is up to date.');
        }

        return self::SUCCESS;
    }
}
