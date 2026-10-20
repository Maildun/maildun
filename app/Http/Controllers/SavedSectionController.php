<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveSavedSectionRequest;
use App\Models\SavedSection;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Saved sections behind "Your sections" in the block editor's palette. The
 * editor pages reload only their `savedSections` prop after each change.
 */
class SavedSectionController extends Controller
{
    public function store(SaveSavedSectionRequest $request, Team $currentTeam): RedirectResponse
    {
        // validated() keeps only the keys that have rules, which would drop
        // props, style and children, so the checked input is stored whole.
        /** @var list<array<string, mixed>> $blocks */
        $blocks = $request->input('blocks');

        $currentTeam->savedSections()->create([
            'created_by' => $request->user()?->id,
            'name' => $request->validated('name'),
            'blocks' => array_map($this->withoutIds(...), $blocks),
        ]);

        return back();
    }

    public function update(SaveSavedSectionRequest $request, Team $currentTeam, SavedSection $savedSection): RedirectResponse
    {
        $savedSection->update(['name' => $request->validated('name')]);

        return back();
    }

    public function destroy(Team $currentTeam, SavedSection $savedSection): RedirectResponse
    {
        Gate::authorize('delete', $savedSection);

        $savedSection->delete();

        return back();
    }

    /**
     * Ids are dropped so a section can go into the same design more than once.
     *
     * @param  array<string, mixed>  $block
     * @return array<string, mixed>
     */
    private function withoutIds(array $block): array
    {
        unset($block['id']);

        if (isset($block['children']) && is_array($block['children'])) {
            $block['children'] = array_map(
                fn (mixed $child): mixed => is_array($child) ? $this->withoutIds($child) : $child,
                $block['children'],
            );
        }

        return $block;
    }
}
