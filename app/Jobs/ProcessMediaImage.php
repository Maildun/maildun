<?php

namespace App\Jobs;

use App\Actions\Media\ConvertImageToWebp;
use App\Enums\MediaStatus;
use App\Enums\StorageBackend;
use App\Models\Media;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class ProcessMediaImage implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * The disk the upload was written to at dispatch. A pending row's recorded
     * disk wins over this, so a backend switch that repoints the row mid-flight
     * is followed rather than ignored.
     */
    public string $sourceDisk = 'local';

    public int $tries = 2;

    /** @var list<int> */
    public array $backoff = [5, 30];

    public int $timeout = 60;

    public function __construct(
        public int $mediaId,
        public string $sourcePath,
        ?string $sourceDisk = null,
    ) {
        $this->sourceDisk = $sourceDisk ?? StorageBackend::current()->privateDisk();
    }

    public function uniqueId(): string
    {
        return (string) $this->mediaId;
    }

    public function handle(?ConvertImageToWebp $converter = null): void
    {
        $converter ??= new ConvertImageToWebp;

        $media = Media::query()->with('team')->find($this->mediaId);

        if ($media === null || $media->upload_path !== $this->sourcePath) {
            Storage::disk($this->sourceDisk)->delete($this->sourcePath);

            return;
        }

        $sourceDisk = $media->disk ?? $this->sourceDisk;
        $converted = $converter->handle((string) Storage::disk($sourceDisk)->get($this->sourcePath));
        $imagePath = $media->directory().'/'.$media->uuid.'.webp';

        if (! Storage::disk('public')->put($imagePath, $converted['contents'])) {
            throw new RuntimeException('Unable to store the optimized media file.');
        }

        $oldPath = null;
        $wasPromoted = false;

        DB::transaction(function () use ($imagePath, $converted, &$oldPath, &$wasPromoted): void {
            $media = Media::query()
                ->whereKey($this->mediaId)
                ->lockForUpdate()
                ->first();

            if ($media === null || $media->upload_path !== $this->sourcePath) {
                return;
            }

            $oldPath = $media->path;
            $media->forceFill([
                'disk' => 'public',
                'path' => $imagePath,
                'upload_path' => null,
                'mime_type' => 'image/webp',
                'extension' => 'webp',
                'size' => strlen($converted['contents']),
                'width' => $converted['width'],
                'height' => $converted['height'],
                'status' => MediaStatus::Ready,
                'failed_reason' => null,
            ])->save();
            $wasPromoted = true;
        });

        Storage::disk($sourceDisk)->delete($this->sourcePath);

        if (! $wasPromoted) {
            Storage::disk('public')->delete($imagePath);

            return;
        }

        if ($oldPath && $oldPath !== $imagePath) {
            Storage::disk('public')->delete($oldPath);
        }
    }

    public function failed(?Throwable $exception): void
    {
        $sourceDisk = Media::query()
            ->whereKey($this->mediaId)
            ->where('upload_path', $this->sourcePath)
            ->value('disk') ?? $this->sourceDisk;

        Media::query()
            ->whereKey($this->mediaId)
            ->where('upload_path', $this->sourcePath)
            ->update([
                'status' => MediaStatus::Failed->value,
                'failed_reason' => $exception?->getMessage() ?: 'Unable to convert the image to WebP.',
                'upload_path' => null,
            ]);

        Storage::disk($sourceDisk)->delete($this->sourcePath);
    }
}
