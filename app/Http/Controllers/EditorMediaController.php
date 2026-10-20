<?php

namespace App\Http\Controllers;

use App\Actions\Media\StoreTeamMedia;
use App\Http\Requests\StoreMediaRequest;
use App\Models\Media;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Uploads from an image block in the block editor. The editor waits for a
 * URL, so these answer with JSON; while a JPEG or PNG is still converting to
 * WebP on the queue, the editor polls `show` until the file is ready.
 */
class EditorMediaController extends Controller
{
    public function store(StoreMediaRequest $request, Team $currentTeam, StoreTeamMedia $store): JsonResponse
    {
        $files = $request->file('files');
        $files = is_array($files) ? array_values($files) : [];

        $media = $store->handle($currentTeam, $request->user(), array_slice($files, 0, 1))->first();

        abort_unless($media instanceof Media, 422);

        return response()->json(['media' => $media->toInertia()], 201);
    }

    public function show(Team $currentTeam, Media $media): JsonResponse
    {
        Gate::authorize('view', $media);

        return response()->json(['media' => $media->refresh()->toInertia()]);
    }
}
