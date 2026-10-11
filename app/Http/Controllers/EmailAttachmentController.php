<?php

namespace App\Http\Controllers;

use App\Actions\Emails\StoreEmailAttachments;
use App\Enums\EmailStatus;
use App\Http\Requests\StoreEmailAttachmentRequest;
use App\Models\Email;
use App\Models\EmailAttachment;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class EmailAttachmentController extends Controller
{
    public function store(StoreEmailAttachmentRequest $request, Team $currentTeam, Email $email, StoreEmailAttachments $store): RedirectResponse
    {
        /** @var list<UploadedFile> $files */
        $files = $request->file('attachments', []);
        $request->ensureDraftCanAccept(count($files));
        $store->handle($email, $files);

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
