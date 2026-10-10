<?php

namespace App\Actions\Media;

use App\Models\Media;
use App\Models\Team;
use Illuminate\Support\Facades\Gate;

class BuildMediaLibrary
{
    /**
     * The recent media shown by the email builder's media library dialog.
     *
     * @return array{
     *     items: list<array<string, mixed>>,
     *     canManage: bool,
     *     canUpload: bool,
     *     atLimit: bool,
     *     convertUploadsToWebp: bool,
     *     hasMore: bool
     * }
     */
    public function handle(Team $team): array
    {
        Gate::authorize('viewAny', [Media::class, $team]);

        $media = $team->media()
            ->with(['category', 'tags'])
            ->latest()
            ->limit(61)
            ->get();
        $canManage = Gate::allows('create', [Media::class, $team]);

        return [
            'items' => array_values($media
                ->take(60)
                ->map(fn (Media $item): array => $item->toInertia())
                ->all()),
            'canManage' => $canManage,
            'canUpload' => $canManage,
            'atLimit' => false,
            'convertUploadsToWebp' => $team->convert_uploads_to_webp,
            'hasMore' => $media->count() > 60,
        ];
    }
}
