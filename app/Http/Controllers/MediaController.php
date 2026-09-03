<?php

namespace App\Http\Controllers;

use App\Actions\Media\DeleteTeamMedia;
use App\Actions\Media\StoreTeamMedia;
use App\Actions\Media\UpdateTeamMedia;
use App\Http\Requests\StoreMediaRequest;
use App\Http\Requests\UpdateMediaRequest;
use App\Models\Media;
use App\Models\MediaCategory;
use App\Models\MediaTag;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class MediaController extends Controller
{
    public function index(Request $request, Team $currentTeam): Response
    {
        Gate::authorize('viewAny', [Media::class, $currentTeam]);

        $search = $request->string('q')->trim()->value();
        $category = $request->string('category')->trim()->value();
        $tag = $request->string('tag')->trim()->value();

        $media = $currentTeam->media()
            ->with(['category', 'tags'])
            ->latest()
            ->when($search !== '', function ($query) use ($search) {
                $escaped = addcslashes($search, '%_\\');

                return $query->whereLike('name', '%'.$escaped.'%');
            })
            ->when($category === 'uncategorized', fn ($query) => $query->whereNull('media_category_id'))
            ->when($category !== '' && $category !== 'uncategorized', function ($query) use ($category) {
                return $query->whereHas(
                    'category',
                    fn ($categoryQuery) => $categoryQuery->where('uuid', $category),
                );
            })
            ->when($tag !== '', function ($query) use ($tag) {
                return $query->whereHas(
                    'tags',
                    fn ($tagQuery) => $tagQuery->where('uuid', $tag),
                );
            })
            ->paginate(36)
            ->withQueryString()
            ->through(fn (Media $item): array => $item->toInertia());

        return Inertia::render('media/index', [
            'media' => $media,
            'filters' => [
                'q' => $search,
                'category' => $category,
                'tag' => $tag,
            ],
            'categories' => $currentTeam->mediaCategories()
                ->orderBy('name')
                ->get()
                ->map(fn (MediaCategory $item): array => [
                    'uuid' => $item->uuid,
                    'name' => $item->name,
                ]),
            'tags' => $currentTeam->mediaTags()
                ->orderBy('name')
                ->get()
                ->map(fn (MediaTag $item): array => [
                    'uuid' => $item->uuid,
                    'name' => $item->name,
                ]),
            'convertUploadsToWebp' => $currentTeam->convert_uploads_to_webp,
            'canManage' => Gate::allows('create', [Media::class, $currentTeam]),
        ]);
    }

    public function store(StoreMediaRequest $request, Team $currentTeam, StoreTeamMedia $store): RedirectResponse
    {
        $files = $request->file('files');
        $files = is_array($files) ? array_values($files) : [];

        $store->handle($currentTeam, $request->user(), $files);

        return back();
    }

    public function update(UpdateMediaRequest $request, Team $currentTeam, Media $media, UpdateTeamMedia $update): RedirectResponse
    {
        /** @var array{name: string, alt?: string|null, category?: string|null, tags?: list<string>} $data */
        $data = $request->validated();

        $update->handle($currentTeam, $media, $data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Media updated.')]);

        return back();
    }

    public function destroy(Team $currentTeam, Media $media, DeleteTeamMedia $delete): RedirectResponse
    {
        Gate::authorize('delete', $media);

        $delete->handle($media);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Media deleted.')]);

        return back();
    }
}
