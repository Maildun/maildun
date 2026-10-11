<?php

namespace App\Actions\Emails;

use App\Enums\EmailStatus;
use App\Enums\StorageBackend;
use App\Models\Email;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class StoreEmailAttachments
{
    /**
     * Store uploaded files as attachments of a draft campaign.
     *
     * The campaign row is locked so two concurrent uploads, or an upload racing
     * a send, cannot slip past the draft check or the 10-attachment cap. Files
     * already written to the private disk are removed again when anything fails.
     *
     * @param  list<UploadedFile>  $files
     */
    public function handle(Email $email, array $files): void
    {
        $disk = StorageBackend::current()->privateDisk();
        $paths = [];

        try {
            DB::transaction(function () use ($email, $files, $disk, &$paths): void {
                $lockedEmail = Email::query()->lockForUpdate()->findOrFail($email->id);
                abort_unless($lockedEmail->status === EmailStatus::Draft, 409, __('A queued campaign can no longer be edited.'));

                if ($lockedEmail->attachments()->count() + count($files) > 10) {
                    throw ValidationException::withMessages(['attachments' => __('A campaign may have up to 10 attachments.')]);
                }

                foreach ($files as $file) {
                    $path = $file->store('email-attachments/'.$lockedEmail->team->uuid.'/'.$lockedEmail->uuid, $disk);
                    abort_unless(is_string($path), 500, __('The attachment could not be stored.'));
                    $paths[] = $path;

                    $lockedEmail->attachments()->create([
                        'disk' => $disk,
                        'path' => $path,
                        'original_name' => Str::limit(basename($file->getClientOriginalName()), 255, ''),
                        'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
                        'size' => $file->getSize(),
                    ]);
                }
            });
        } catch (Throwable $exception) {
            Storage::disk($disk)->delete($paths);

            throw $exception;
        }
    }
}
