<?php

namespace App\Jobs;

use App\Actions\Media\ConvertImageToWebp;
use App\Enums\StorageBackend;
use App\Models\SubscribeForm;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ProcessSubscribeFormImage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The disk the upload was written to at dispatch. A row's recorded
     * image_upload_disk wins over this, so a backend switch that repoints the
     * row mid-flight is followed rather than ignored.
     */
    public string $sourceDisk = 'local';

    public int $tries = 2;

    /** @var list<int> */
    public array $backoff = [5, 30];

    public int $timeout = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $subscribeFormId,
        public string $sourcePath,
        ?string $sourceDisk = null,
    ) {
        $this->sourceDisk = $sourceDisk ?? StorageBackend::current()->privateDisk();
    }

    /**
     * Execute the job.
     */
    public function handle(?ConvertImageToWebp $converter = null): void
    {
        $subscribeForm = SubscribeForm::find($this->subscribeFormId);

        if ($subscribeForm === null || $subscribeForm->image_upload_path !== $this->sourcePath) {
            Storage::disk($this->sourceDisk)->delete($this->sourcePath);

            return;
        }

        $sourceDisk = $subscribeForm->image_upload_disk ?? $this->sourceDisk;
        $imagePath = $this->storeWebpImage($converter ?? new ConvertImageToWebp, $sourceDisk);

        $oldImagePath = null;
        $wasPromoted = false;

        DB::transaction(function () use ($imagePath, &$oldImagePath, &$wasPromoted): void {
            $subscribeForm = SubscribeForm::whereKey($this->subscribeFormId)
                ->lockForUpdate()
                ->first();

            if ($subscribeForm === null || $subscribeForm->image_upload_path !== $this->sourcePath) {
                return;
            }

            $oldImagePath = $subscribeForm->image_path;
            $subscribeForm->forceFill([
                'image_path' => $imagePath,
                'image_upload_path' => null,
                'image_upload_disk' => null,
                'image_url' => null,
            ])->save();
            $wasPromoted = true;
        });

        Storage::disk($sourceDisk)->delete($this->sourcePath);

        if (! $wasPromoted) {
            Storage::disk('public')->delete($imagePath);

            return;
        }

        if ($oldImagePath) {
            Storage::disk('public')->delete($oldImagePath);
        }
    }

    public function failed(?Throwable $exception): void
    {
        $sourceDisk = SubscribeForm::whereKey($this->subscribeFormId)
            ->where('image_upload_path', $this->sourcePath)
            ->value('image_upload_disk') ?? $this->sourceDisk;

        SubscribeForm::whereKey($this->subscribeFormId)
            ->where('image_upload_path', $this->sourcePath)
            ->update(['image_upload_path' => null, 'image_upload_disk' => null]);

        Storage::disk($sourceDisk)->delete($this->sourcePath);
    }

    private function storeWebpImage(ConvertImageToWebp $converter, string $sourceDisk): string
    {
        $converted = $converter->handle(
            (string) Storage::disk($sourceDisk)->get($this->sourcePath),
            maxLongEdge: null,
        );

        $imagePath = 'subscribe-form-images/'.Str::uuid().'.webp';

        if (! Storage::disk('public')->put($imagePath, $converted['contents'])) {
            throw new RuntimeException('Unable to store the optimized subscribe form image.');
        }

        return $imagePath;
    }
}
