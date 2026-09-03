<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveAudienceAttributeRequest;
use App\Models\Audience;
use App\Models\AudienceAttribute;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
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
        $audienceAttribute->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Attribute updated.')]);

        return back();
    }

    public function destroy(Team $currentTeam, Audience $audience, AudienceAttribute $audienceAttribute): RedirectResponse
    {
        Gate::authorize('delete', $audienceAttribute);
        $audienceAttribute->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Attribute deleted.')]);

        return back();
    }
}
