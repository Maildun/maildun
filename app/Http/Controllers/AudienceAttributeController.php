<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReorderAudienceAttributesRequest;
use App\Http\Requests\SaveAudienceAttributeRequest;
use App\Jobs\MoveAudienceAttributeValues;
use App\Models\Audience;
use App\Models\AudienceAttribute;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class AudienceAttributeController extends Controller
{
    public function store(SaveAudienceAttributeRequest $request, Team $currentTeam, Audience $audience): RedirectResponse
    {
        $audience->audienceAttributes()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Attribute created.')]);

        return back();
    }

    public function update(SaveAudienceAttributeRequest $request, Team $currentTeam, Audience $audience, AudienceAttribute $audienceAttribute): RedirectResponse
    {
        $previousKey = $audienceAttribute->key;
        $audienceAttribute->update($request->validated());

        if ($audienceAttribute->key !== $previousKey) {
            MoveAudienceAttributeValues::dispatch($audience->id, $previousKey, $audienceAttribute->id);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Attribute updated.')]);

        return back();
    }

    /**
     * Save a new field order. Position drives the order on hosted forms, the
     * form editor preview and import mapping.
     */
    public function reorder(ReorderAudienceAttributesRequest $request, Team $currentTeam, Audience $audience): RedirectResponse
    {
        /** @var list<string> $uuids */
        $uuids = $request->validated('attributes');

        DB::transaction(function () use ($audience, $uuids): void {
            foreach ($uuids as $index => $uuid) {
                $audience->audienceAttributes()
                    ->where('uuid', $uuid)
                    ->update(['position' => $index + 1]);
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Attribute order saved.')]);

        return back();
    }

    public function destroy(Team $currentTeam, Audience $audience, AudienceAttribute $audienceAttribute): RedirectResponse
    {
        Gate::authorize('delete', $audienceAttribute);
        $audienceAttribute->delete();
        MoveAudienceAttributeValues::dispatch($audience->id, $audienceAttribute->key);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Attribute deleted.')]);

        return back();
    }
}
