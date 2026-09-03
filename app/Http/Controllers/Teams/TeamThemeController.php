<?php

namespace App\Http\Controllers\Teams;

use App\Enums\TeamBrandColor;
use App\Enums\TeamBrandFont;
use App\Enums\TeamBrandInputStyle;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teams\SaveTeamThemeRequest;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TeamThemeController extends Controller
{
    public function edit(Request $request, Team $team): Response
    {
        Gate::authorize('update', $team);

        return Inertia::render('teams/theme', [
            'team' => $request->user()->toUserTeam($team),
            'permissions' => $request->user()->toTeamPermissions($team),
            'colors' => TeamBrandColor::options(),
            'fonts' => TeamBrandFont::options(),
            'inputStyles' => TeamBrandInputStyle::options(),
        ]);
    }

    public function update(SaveTeamThemeRequest $request, Team $team): RedirectResponse
    {
        Gate::authorize('update', $team);

        $team->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Form theme defaults updated.')]);

        return back();
    }
}
