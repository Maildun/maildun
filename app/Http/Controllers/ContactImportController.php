<?php

namespace App\Http\Controllers;

use App\Enums\StorageBackend;
use App\Http\Requests\StoreContactImportRequest;
use App\Jobs\ProcessContactImport;
use App\Models\Audience;
use App\Models\ContactImport;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class ContactImportController extends Controller
{
    public function store(StoreContactImportRequest $request, Team $currentTeam, ?Audience $audience = null): RedirectResponse
    {
        $file = $request->file('file');
        $disk = StorageBackend::current()->privateDisk();
        $path = $file->store('contact-imports/'.$currentTeam->uuid, $disk);

        $contactImport = ContactImport::query()->create([
            'team_id' => $currentTeam->id,
            'audience_id' => $audience?->id,
            'uploaded_by' => $request->user()?->id,
            'consent_ip' => $request->ip(),
            'original_name' => $file->getClientOriginalName(),
            'disk' => $disk,
            'path' => $path,
        ]);

        ProcessContactImport::dispatch($contactImport->id);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Import queued. You can keep working while it runs.'),
        ]);

        return back();
    }
}
