<?php

namespace App\Services;

use App\Data\StorageMigrationReport;
use App\Enums\StorageBackend;
use App\Models\EmailAttachment;
use App\Models\Media;
use App\Models\SubscribeForm;
use Closure;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Copies every stored file from one storage backend to the other so
 * FILESYSTEM_DISK can be flipped without stranding existing uploads.
 *
 * Object keys are identical on both backends, so public assets need no database
 * change — their URL is derived from the active public disk at read time. Only
 * rows that name a concrete private disk (email attachments, media still
 * awaiting WebP conversion) are repointed.
 */
class StorageBackendMigrator
{
    /**
     * Files Laravel ships to keep the storage directories in git.
     *
     * @var list<string>
     */
    private const IGNORED_FILES = ['.gitignore'];

    /**
     * Names of the .env variables the target backend still needs.
     *
     * @return list<string>
     */
    public function missingConfiguration(StorageBackend $backend): array
    {
        $missing = [];

        foreach ($backend->requiredConfiguration() as $configKey => $variable) {
            if (blank(config($configKey))) {
                $missing[] = $variable;
            }
        }

        return $missing;
    }

    /**
     * Uploads still mid-conversion. Their rows are repointed like any other, so
     * the queued job follows them, but the old file must survive until the job
     * runs — which is why a sync copies rather than moves.
     */
    public function inFlightUploads(): int
    {
        return Media::query()->whereNotNull('upload_path')->count()
            + SubscribeForm::query()->whereNotNull('image_upload_path')->count();
    }

    /**
     * Number of real files a backend holds, using the same ignore list as a
     * copy so a fresh checkout does not report its .gitignore stubs as content.
     */
    public function countFiles(StorageBackend $backend): int
    {
        $total = 0;

        foreach ([$backend->privateDisk(), $backend->publicDisk()] as $disk) {
            foreach (Storage::disk($disk)->allFiles() as $path) {
                if (! $this->isIgnored($path)) {
                    $total++;
                }
            }
        }

        return $total;
    }

    /**
     * Round-trip a throwaway object through both of a backend's disks so bad
     * credentials, a missing bucket, or a wrong endpoint surface now rather
     * than as broken images later.
     *
     * @return array<string, string> Disk name to failure reason, empty when healthy.
     */
    public function probe(StorageBackend $backend): array
    {
        $failures = [];
        $key = 'maildun-storage-check/'.Str::uuid().'.txt';
        $payload = 'maildun storage check';

        foreach ([$backend->privateDisk(), $backend->publicDisk()] as $disk) {
            try {
                $filesystem = Storage::disk($disk);

                if (! $filesystem->put($key, $payload)) {
                    $failures[$disk] = 'The disk rejected a test write.';

                    continue;
                }

                if ($filesystem->get($key) !== $payload) {
                    $failures[$disk] = 'A test file was written but read back differently.';
                }

                $filesystem->delete($key);
            } catch (Throwable $exception) {
                $failures[$disk] = $exception->getMessage();
            }
        }

        return $failures;
    }

    /**
     * @param  (Closure(string, string): void)|null  $onFile  Receives the disk name and path of each copied file.
     */
    public function migrate(
        StorageBackend $from,
        StorageBackend $to,
        bool $overwrite = false,
        bool $dryRun = false,
        ?Closure $onFile = null,
    ): StorageMigrationReport {
        $copied = [];
        $skipped = [];
        $failures = [];

        $diskPairs = [
            $from->privateDisk() => $to->privateDisk(),
            $from->publicDisk() => $to->publicDisk(),
        ];

        foreach ($diskPairs as $sourceDisk => $targetDisk) {
            $source = Storage::disk($sourceDisk);
            $target = Storage::disk($targetDisk);
            $copied[$sourceDisk] = 0;
            $skipped[$sourceDisk] = 0;

            foreach ($source->allFiles() as $path) {
                if ($this->isIgnored($path)) {
                    continue;
                }

                if (! $overwrite && $target->exists($path)) {
                    $skipped[$sourceDisk]++;

                    continue;
                }

                if (! $dryRun) {
                    try {
                        $this->copyFile($source, $target, $path);
                    } catch (Throwable $exception) {
                        $failures[] = $sourceDisk.'/'.$path.': '.$exception->getMessage();

                        continue;
                    }
                }

                $copied[$sourceDisk]++;

                if ($onFile !== null) {
                    $onFile($sourceDisk, $path);
                }
            }
        }

        return new StorageMigrationReport(
            copiedByDisk: $copied,
            skippedByDisk: $skipped,
            failures: $failures,
            repointedAttachments: $dryRun
                ? EmailAttachment::query()->where('disk', $from->privateDisk())->count()
                : $this->repointAttachments($from, $to),
            repointedMedia: $dryRun
                ? $this->pendingMediaQuery($from)->count()
                : $this->pendingMediaQuery($from)->update(['disk' => $to->privateDisk()]),
            repointedSubscribeForms: $dryRun
                ? $this->pendingSubscribeFormQuery($from)->count()
                : $this->pendingSubscribeFormQuery($from)->update(['image_upload_disk' => $to->privateDisk()]),
        );
    }

    private function isIgnored(string $path): bool
    {
        return in_array(basename($path), self::IGNORED_FILES, true);
    }

    private function copyFile(Filesystem $source, Filesystem $target, string $path): void
    {
        $stream = $source->readStream($path);

        if ($stream === null) {
            throw new RuntimeException('The source file could not be opened.');
        }

        try {
            if (! $target->writeStream($path, $stream)) {
                throw new RuntimeException('The target disk rejected the write.');
            }
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    private function repointAttachments(StorageBackend $from, StorageBackend $to): int
    {
        return EmailAttachment::query()
            ->where('disk', $from->privateDisk())
            ->update(['disk' => $to->privateDisk()]);
    }

    /**
     * Media whose original upload is still on a private disk. Ready media keeps
     * disk="public" and must not be touched.
     *
     * @return Builder<Media>
     */
    private function pendingMediaQuery(StorageBackend $from): Builder
    {
        return Media::query()
            ->whereNotNull('upload_path')
            ->where('disk', $from->privateDisk());
    }

    /**
     * Subscribe form artwork still awaiting WebP conversion. The promoted
     * image_path lives on the public disk and must not be touched.
     *
     * @return Builder<SubscribeForm>
     */
    private function pendingSubscribeFormQuery(StorageBackend $from): Builder
    {
        return SubscribeForm::query()
            ->whereNotNull('image_upload_path')
            ->where('image_upload_disk', $from->privateDisk());
    }
}
