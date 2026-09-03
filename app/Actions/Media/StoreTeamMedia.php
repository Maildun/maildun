<?php

namespace App\Actions\Media;

use App\Enums\MediaStatus;
use App\Enums\StorageBackend;
use App\Jobs\ProcessMediaImage;
use App\Models\Media;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use RuntimeException;

class StoreTeamMedia
{
    /**
     * Store one or more uploads for the team, queueing WebP conversion when enabled.
     *
     * @param  list<UploadedFile>  $files
     * @return Collection<int, Media>
     */
    public function handle(Team $team, User $user, array $files): Collection
    {
        return Collection::make($files)
            ->map(fn (UploadedFile $file): Media => $this->storeFile($team, $user, $file))
            ->values();
    }

    private function storeFile(Team $team, User $user, UploadedFile $file): Media
    {
        $extension = $this->extensionFor($file);
        $originalName = Str::limit($file->getClientOriginalName(), 255, '');
        $dimensions = @getimagesize($file->getRealPath()) ?: [null, null];

        $media = $team->media()->create([
            'uploaded_by' => $user->id,
            'name' => $originalName !== '' ? $originalName : 'image.'.$extension,
            'mime_type' => $file->getMimeType(),
            'extension' => $extension,
            'size' => $file->getSize(),
            'width' => is_int($dimensions[0]) ? $dimensions[0] : null,
            'height' => is_int($dimensions[1]) ? $dimensions[1] : null,
            'status' => MediaStatus::Processing,
        ]);

        $media->setRelation('team', $team);

        if ($this->shouldConvert($team, $file, $extension)) {
            $sourceDisk = StorageBackend::current()->privateDisk();
            $uploadPath = $file->storeAs($media->pendingDirectory(), $media->uuid.'.'.$extension, $sourceDisk);

            if ($uploadPath === false) {
                $media->delete();

                throw new RuntimeException('Unable to store the uploaded media file.');
            }

            $media->forceFill([
                'disk' => $sourceDisk,
                'upload_path' => $uploadPath,
                'status' => MediaStatus::Processing,
            ])->save();

            ProcessMediaImage::dispatch($media->id, $uploadPath, $sourceDisk);

            return $media;
        }

        $path = $file->storeAs($media->directory(), $media->uuid.'.'.$extension, 'public');

        if ($path === false) {
            $media->delete();

            throw new RuntimeException('Unable to store the uploaded media file.');
        }

        $media->forceFill([
            'disk' => 'public',
            'path' => $path,
            'upload_path' => null,
            'status' => MediaStatus::Ready,
        ])->save();

        return $media;
    }

    private function shouldConvert(Team $team, UploadedFile $file, string $extension): bool
    {
        if (! $team->convert_uploads_to_webp) {
            return false;
        }

        $mime = (string) $file->getMimeType();

        return in_array($extension, ['jpg', 'jpeg', 'png'], true)
            || in_array($mime, ['image/jpeg', 'image/png'], true);
    }

    private function extensionFor(UploadedFile $file): string
    {
        $extension = strtolower((string) ($file->guessExtension() ?: $file->getClientOriginalExtension()));

        return $extension !== '' ? $extension : 'bin';
    }
}
