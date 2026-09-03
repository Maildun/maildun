<?php

namespace App\Console\Commands;

use App\Data\StorageMigrationReport;
use App\Enums\StorageBackend;
use App\Services\StorageBackendMigrator;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('storage:sync {--to= : The backend to copy files into (local or s3)} {--overwrite : Replace files that already exist on the target} {--dry-run : Report what would be copied without writing anything}')]
#[Description('Copy every uploaded file between the local filesystem and S3-compatible storage')]
class SyncStorageBackendCommand extends Command
{
    /**
     * Switching FILESYSTEM_DISK only changes where new uploads land; existing
     * files stay on the old backend and their URLs break. Run this first with
     * the target credentials already in .env, then flip FILESYSTEM_DISK.
     */
    public function handle(StorageBackendMigrator $migrator): int
    {
        $target = $this->resolveTarget();

        if ($target === null) {
            return self::FAILURE;
        }

        $source = $target->other();

        if (! $this->ensureConfigured($migrator, $target)) {
            return self::FAILURE;
        }

        $this->warnAboutInFlightUploads($migrator);

        $dryRun = (bool) $this->option('dry-run');

        $this->components->info(sprintf(
            '%s files from the %s to %s.',
            $dryRun ? 'Would copy' : 'Copying',
            $source->label(),
            $target->label(),
        ));

        $report = $migrator->migrate(
            from: $source,
            to: $target,
            overwrite: (bool) $this->option('overwrite'),
            dryRun: $dryRun,
            onFile: $this->output->isVerbose()
                ? function (string $disk, string $path): void {
                    $this->line("  <fg=gray>{$disk}</> {$path}");
                }
            : null,
        );

        $this->renderReport($report, $source, $target, $dryRun);

        if ($report->failed()) {
            return self::FAILURE;
        }

        if (! $dryRun) {
            $this->renderNextSteps($target);
        }

        return self::SUCCESS;
    }

    private function resolveTarget(): ?StorageBackend
    {
        $option = $this->option('to');

        if (! is_string($option)) {
            $option = $this->choice(
                'Which backend should the files be copied into?',
                [StorageBackend::Local->value, StorageBackend::S3->value],
                StorageBackend::current()->value,
            );
        }

        $target = is_string($option) ? StorageBackend::tryFrom($option) : null;

        if ($target === null) {
            $this->components->error('--to must be either "local" or "s3".');
        }

        return $target;
    }

    private function ensureConfigured(StorageBackendMigrator $migrator, StorageBackend $target): bool
    {
        $missing = array_merge(
            $migrator->missingConfiguration($target),
            $migrator->missingConfiguration($target->other()),
        );

        if ($missing === []) {
            return true;
        }

        $this->components->error('Both backends must be configured before files can be copied.');
        $this->components->bulletList($missing);
        $this->components->warn('Set these in .env, then run this command again.');

        return false;
    }

    private function warnAboutInFlightUploads(StorageBackendMigrator $migrator): void
    {
        $inFlight = $migrator->inFlightUploads();

        if ($inFlight === 0) {
            return;
        }

        $this->components->warn(sprintf(
            '%d upload(s) are still being converted. Their rows are repointed too, so keep the old files in place until the queue drains.',
            $inFlight,
        ));
    }

    private function renderReport(
        StorageMigrationReport $report,
        StorageBackend $source,
        StorageBackend $target,
        bool $dryRun,
    ): void {
        $rows = [];

        foreach ($report->copiedByDisk as $disk => $count) {
            $rows[] = [$disk, $count, $report->skippedByDisk[$disk] ?? 0];
        }

        $this->table([$dryRun ? 'Source disk' : 'Copied from', 'Files', 'Already present'], $rows);

        $repointed = [
            'Email attachments' => $report->repointedAttachments,
            'Processing media' => $report->repointedMedia,
            'Processing form artwork' => $report->repointedSubscribeForms,
        ];

        foreach (array_filter($repointed) as $label => $count) {
            $this->components->twoColumnDetail(
                $label.($dryRun ? ' to repoint' : ' repointed'),
                (string) $count,
            );
        }

        foreach ($report->failures as $failure) {
            $this->components->error($failure);
        }

        if ($report->failed()) {
            return;
        }

        $this->components->info(sprintf(
            '%d file(s) %s from %s to %s, %d already present.',
            $report->copied(),
            $dryRun ? 'would be copied' : 'copied',
            $source->value,
            $target->value,
            $report->skipped(),
        ));
    }

    private function renderNextSteps(StorageBackend $target): void
    {
        $publicUrlEnvironmentKey = config('filesystems.object_storage_provider') === 'r2'
            ? 'R2_PUBLIC_URL'
            : 'AWS_PUBLIC_URL';

        $this->components->info('Next steps:');
        $this->components->bulletList([
            'Set FILESYSTEM_DISK='.$target->value.' in .env.',
            $target === StorageBackend::Local
                ? 'Run "php artisan storage:link" so /storage serves the public disk.'
                : "Confirm {$publicUrlEnvironmentKey} resolves publicly — the public bucket needs a bucket policy, CDN, or R2 custom domain.",
            'Run "php artisan config:clear" (or config:cache) and restart the queue workers.',
            'Absolute media URLs already pasted into email content still point at the old host and need updating by hand.',
        ]);
    }
}
