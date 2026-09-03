<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateMediaSettingsRequest;
use App\Models\Media;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class MediaSettingsController extends Controller
{
    public function edit(Team $currentTeam): Response
    {
        Gate::authorize('viewAny', [Media::class, $currentTeam]);

        return Inertia::render('media/settings', [
            'convertUploadsToWebp' => $currentTeam->convert_uploads_to_webp,
            'canManage' => Gate::allows('updateSettings', [Media::class, $currentTeam]),
        ]);
    }

    public function update(UpdateMediaSettingsRequest $request, Team $currentTeam): RedirectResponse
    {
        $currentTeam->update($request->safe()->only(['convert_uploads_to_webp']));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Media settings updated.')]);

        return back();
    }
}
