<?php

namespace App\Console\Commands;

use App\Enums\StorageBackend;
use App\Services\EnvironmentFile;
use App\Services\StorageBackendMigrator;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

#[Signature('storage:configure {--backend= : Skip the prompt and use "local" or "s3"} {--skip-verify : Do not round-trip a test file through the chosen backend}')]
#[Description('Choose where uploads are stored and write the matching .env values')]
class ConfigureStorageCommand extends Command
{
    /**
     * The install-time entry point for storage. It writes .env, proves the
     * chosen backend actually works, and hands off to storage:sync when files
     * are already sitting on the other backend.
     */
    public function handle(StorageBackendMigrator $migrator, EnvironmentFile $environment): int
    {
        $backend = $this->resolveBackend();

        if ($backend === null) {
            return self::FAILURE;
        }

        $values = ['FILESYSTEM_DISK' => $backend->value] + match ($backend) {
            StorageBackend::Local => [],
            StorageBackend::S3 => $this->askForS3Credentials(),
        };

        $environment->put($values);
        $environment->applyToRuntime($values);
        $this->reloadFilesystems();

        $this->components->info('Wrote '.count($values).' value(s) to .env.');

        if (! $this->option('skip-verify') && ! $this->verify($migrator, $backend)) {
            return self::FAILURE;
        }

        if ($backend === StorageBackend::Local) {
            $this->linkPublicDirectory();
        }

        $this->offerToMoveExistingFiles($migrator, $backend);

        $this->components->info('Storage is set to '.$backend->label().'.');

        return self::SUCCESS;
    }

    /**
     * Local public assets are served through the public/storage symlink.
     * storage:link errors out when the link is already there, so re-running
     * this command stays quiet instead of looking like a failure.
     */
    private function linkPublicDirectory(): void
    {
        if (file_exists(public_path('storage'))) {
            $this->components->twoColumnDetail('public/storage', '<fg=green>linked</>');

            return;
        }

        $this->call('storage:link');
    }

    private function resolveBackend(): ?StorageBackend
    {
        $option = $this->option('backend');
        $option = is_string($option) ? $option : $this->choice(
            'Where should uploaded files be stored?',
            [
                StorageBackend::Local->value => 'On this server (storage/app) — no extra services needed',
                StorageBackend::S3->value => 'AWS S3, Cloudflare R2, or another S3-compatible bucket',
            ],
            StorageBackend::current()->value,
        );

        $backend = is_string($option) ? StorageBackend::tryFrom($option) : null;

        if ($backend === null) {
            $this->components->error('--backend must be either "local" or "s3".');
        }

        return $backend;
    }

    /**
     * @return array<string, string>
     */
    private function askForS3Credentials(): array
    {
        $provider = $this->choice(
            'Which S3-compatible service?',
            ['aws' => 'AWS S3', 'r2' => 'Cloudflare R2', 'other' => 'Something else (MinIO, DigitalOcean Spaces, …)'],
            'aws',
        );

        $isAws = $provider === 'aws';
        $isR2 = $provider === 'r2';
        $prefix = $isR2 ? 'R2' : 'AWS';

        $endpoint = $isAws ? '' : $this->askFor(
            $prefix.'_ENDPOINT',
            $isR2
                ? 'S3 API endpoint (https://<ACCOUNT_ID>.r2.cloudflarestorage.com)'
                : 'S3 API endpoint',
        );

        $values = [
            'OBJECT_STORAGE_PROVIDER' => $isR2 ? 'r2' : ($isAws ? 'aws' : 'custom'),
            $prefix.'_ACCESS_KEY_ID' => $this->askFor($prefix.'_ACCESS_KEY_ID', 'Access key ID'),
            $prefix.'_SECRET_ACCESS_KEY' => $this->askFor($prefix.'_SECRET_ACCESS_KEY', 'Secret access key', hidden: true),
            $prefix.'_DEFAULT_REGION' => $this->askFor(
                $prefix.'_DEFAULT_REGION',
                'Region',
                default: $isR2 ? 'auto' : 'us-east-1',
            ),
            $prefix.'_ENDPOINT' => $endpoint,
            $prefix.'_BUCKET' => $this->askFor($prefix.'_BUCKET', 'Bucket for private files (attachments, pending uploads)'),
            $prefix.'_PUBLIC_BUCKET' => $this->askFor($prefix.'_PUBLIC_BUCKET', 'Bucket for public assets (media, logos, avatars)'),
            $prefix.'_USE_PATH_STYLE_ENDPOINT' => $isAws || $isR2 ? 'false' : 'true',
        ];

        /*
         * A plain AWS bucket serves its own public URL, so this is optional
         * there. Every other endpoint needs a CDN or custom domain in front.
         */
        $values[$prefix.'_PUBLIC_URL'] = $this->askFor(
            $prefix.'_PUBLIC_URL',
            $isAws
                ? 'Public base URL for assets, without a trailing slash (blank to use the bucket URL)'
                : 'Public base URL for assets, without a trailing slash (custom domain or CDN)',
        );

        return $values;
    }

    private function askFor(string $key, string $question, bool $hidden = false, string $default = ''): string
    {
        $existing = app(EnvironmentFile::class)->get($key);
        $fallback = $existing !== '' ? $existing : $default;

        if ($hidden) {
            $answer = (string) $this->secret(
                $question.($existing !== '' ? ' (blank keeps the current value)' : ''),
            );

            return $answer !== '' ? $answer : $fallback;
        }

        return (string) $this->ask($question, $fallback !== '' ? $fallback : null) ?: '';
    }

    /**
     * config/filesystems.php is re-read so the disks reflect the new .env
     * values, which lets verify and sync run without a second invocation.
     */
    private function reloadFilesystems(): void
    {
        config()->set('filesystems', require config_path('filesystems.php'));

        foreach (array_keys((array) config('filesystems.disks')) as $disk) {
            Storage::forgetDisk((string) $disk);
        }
    }

    private function verify(StorageBackendMigrator $migrator, StorageBackend $backend): bool
    {
        $missing = $migrator->missingConfiguration($backend);

        if ($missing !== []) {
            $this->components->error('These values are still empty:');
            $this->components->bulletList($missing);

            return false;
        }

        $failures = $migrator->probe($backend);

        if ($failures === []) {
            $this->components->info('Test file written, read back, and removed on both disks.');

            return true;
        }

        $this->components->error('The chosen backend is not usable yet:');

        foreach ($failures as $disk => $reason) {
            $this->components->twoColumnDetail($disk, $reason);
        }

        return false;
    }

    private function offerToMoveExistingFiles(StorageBackendMigrator $migrator, StorageBackend $backend): void
    {
        $source = $backend->other();

        try {
            $existing = $migrator->countFiles($source);
        } catch (Throwable) {
            /* The old backend is unreachable, so there is nothing to offer. */
            return;
        }

        if ($existing === 0) {
            return;
        }

        $this->components->warn(sprintf(
            '%d file(s) are still on the %s and will not be reachable until they are copied over.',
            $existing,
            $source->label(),
        ));

        if ($this->confirm('Copy them to '.$backend->label().' now?', true)) {
            $this->call('storage:sync', ['--to' => $backend->value]);
        } else {
            $this->components->info('Run "php artisan storage:sync --to='.$backend->value.'" when you are ready.');
        }
    }
}
