<?php

namespace App\Console\Commands;

use App\Enums\StorageBackend;
use App\Services\StorageBackendMigrator;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('storage:check')]
#[Description('Verify the current storage configuration with a temporary write, read, and delete')]
class CheckStorageCommand extends Command
{
    public function handle(StorageBackendMigrator $migrator): int
    {
        $backend = StorageBackend::current();

        $this->components->info('Checking '.$backend->label().' using the current effective configuration.');

        $missing = $migrator->missingConfiguration($backend);

        if ($missing !== []) {
            $this->components->error('Storage configuration is incomplete:');
            $this->components->bulletList($missing);

            return self::FAILURE;
        }

        $failures = $migrator->probe($backend);

        if ($failures !== []) {
            $this->components->error('Storage check failed:');

            foreach ($failures as $disk => $reason) {
                $this->components->twoColumnDetail($disk, $this->sanitize($reason));
            }

            return self::FAILURE;
        }

        $this->components->twoColumnDetail($backend->privateDisk(), '<fg=green>ready</>');
        $this->components->twoColumnDetail($backend->publicDisk(), '<fg=green>ready</>');
        $this->components->info('Temporary files were written, read back, and removed from both storage roles.');

        return self::SUCCESS;
    }

    private function sanitize(string $reason): string
    {
        $credentials = array_values(array_filter([
            config('filesystems.disks.s3.key'),
            config('filesystems.disks.s3.secret'),
            config('filesystems.disks.s3.token'),
        ], fn (mixed $value): bool => is_string($value) && $value !== ''));

        return str_replace($credentials, '[redacted]', $reason);
    }
}
