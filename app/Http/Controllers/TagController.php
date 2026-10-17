<?php

namespace App\Http\Controllers;

use App\Http\Requests\BulkTagRequest;
use App\Http\Requests\SaveTagRequest;
use App\Models\Automation;
use App\Models\Tag;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TagController extends Controller
{
    public function index(Request $request, Team $team): Response
    {
        Gate::authorize('viewAny', [Tag::class, $team]);

        return Inertia::render('teams/tags', [
            'team' => [
                'id' => $team->id,
                'uuid' => $team->uuid,
                'name' => $team->name,
                'slug' => $team->slug,
                'logo' => $team->logo,
                'isPersonal' => $team->is_personal,
            ],
            'tags' => $team->tags()
                ->withCount('contacts as subscribers_count')
                ->orderByRaw('LOWER(name)')
                ->get()
                ->map(fn (Tag $tag): array => [
                    'uuid' => $tag->uuid,
                    'name' => $tag->name,
                    'color' => $tag->color,
                    'subscribers_count' => $tag->subscribers_count,
                    'created_at' => $tag->created_at?->toISOString(),
                ]),
            'colors' => Tag::COLORS,
            'permissions' => $request->user()->toTeamPermissions($team),
        ]);
    }

    public function store(SaveTagRequest $request, Team $team): RedirectResponse
    {
        $team->tags()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tag created.')]);

        return back();
    }

    public function update(SaveTagRequest $request, Team $team, Tag $tag): RedirectResponse
    {
        $tag->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tag updated.')]);

        return back();
    }

    public function destroy(Team $team, Tag $tag): RedirectResponse
    {
        Gate::authorize('delete', $tag);
        $this->ensureNotInUse($team, collect([$tag]));
        $tag->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tag deleted.')]);

        return back();
    }

    public function bulkDestroy(BulkTagRequest $request, Team $team): RedirectResponse
    {
        Gate::authorize('create', [Tag::class, $team]);

        $tags = $team->tags()->whereIn('uuid', $request->validated('ids'))->get(['id', 'uuid', 'name']);
        $this->ensureNotInUse($team, $tags);

        $team->tags()->whereKey($tags->modelKeys())->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Selected tags deleted.')]);

        return back();
    }

    /**
     * Deleting a tag an automation step names would make that step fail on
     * every run, so the automation has to let go of it first.
     *
     * @param  Collection<int, Tag>  $tags
     */
    private function ensureNotInUse(Team $team, Collection $tags): void
    {
        $namesUsing = Automation::namesUsing($team, array_values($tags->map(fn (Tag $tag): string => $tag->uuid)->all()));

        foreach ($tags as $tag) {
            $reason = Automation::referenceBlockReason($namesUsing[$tag->uuid] ?? [], __('tag'));

            if ($reason !== null) {
                throw ValidationException::withMessages([
                    'tag' => $tags->count() > 1 ? $tag->name.': '.$reason : $reason,
                ]);
            }
        }
    }
}
