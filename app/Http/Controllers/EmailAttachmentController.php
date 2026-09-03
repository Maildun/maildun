<?php

namespace App\Http\Controllers;

use App\Enums\EmailStatus;
use App\Enums\StorageBackend;
use App\Http\Requests\StoreEmailAttachmentRequest;
use App\Models\Email;
use App\Models\EmailAttachment;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EmailAttachmentController extends Controller
{
    public function store(StoreEmailAttachmentRequest $request, Team $currentTeam, Email $email): RedirectResponse
    {
        /** @var list<UploadedFile> $files */
        $files = $request->file('attachments', []);
        $request->ensureDraftCanAccept(count($files));
        $disk = StorageBackend::current()->privateDisk();

        foreach ($files as $file) {
            $path = $file->store('email-attachments/'.$currentTeam->uuid.'/'.$email->uuid, $disk);

            abort_unless(is_string($path), 500, __('The attachment could not be stored.'));

            $email->attachments()->create([
                'disk' => $disk,
                'path' => $path,
                'original_name' => Str::limit(basename($file->getClientOriginalName()), 255, ''),
                'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
                'size' => $file->getSize(),
            ]);
        }

        return back();
    }

    public function destroy(Team $currentTeam, Email $email, EmailAttachment $attachment): RedirectResponse
    {
        Gate::authorize('update', $email);
        abort_unless($email->status === EmailStatus::Draft, 409, __('A queued campaign can no longer be edited.'));
        abort_unless($attachment->email_id === $email->id, 404);

        Storage::disk($attachment->disk)->delete($attachment->path);
        $attachment->delete();

        return back();
    }
}
