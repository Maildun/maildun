<?php

namespace App\Console\Commands;

use App\Services\DbIpDatabaseUpdater;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('emails:update-geolocation-databases {--force : Download even when the installed databases are current}')]
#[Description('Download and safely activate the current DB-IP Lite City and ASN databases')]
class UpdateEmailGeolocationDatabasesCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(DbIpDatabaseUpdater $updater): int
    {
        if (! (bool) config('tracking.geolocation.enabled')) {
            $this->components->info('Email tracking geolocation is disabled.');

            return self::SUCCESS;
        }

        if (! (bool) config('tracking.geolocation.auto_update.enabled')) {
            $this->components->info('Automatic DB-IP Lite updates are disabled.');

            return self::SUCCESS;
        }

        try {
            $results = $updater->update((bool) $this->option('force'));
        } catch (Throwable $exception) {
            report($exception);
            $this->components->error('DB-IP Lite update failed. The failed replacement was not activated; check the application log.');

            return self::FAILURE;
        }

        foreach ($results as $database => $status) {
            $label = $database === 'asn' ? 'ASN' : 'City';
            $detail = $status === 'updated' ? 'Updated' : 'Already current';

            $this->components->twoColumnDetail("{$label} database", $detail);
        }

        return self::SUCCESS;
    }
}
