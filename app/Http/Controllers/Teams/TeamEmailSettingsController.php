<?php

namespace App\Http\Controllers\Teams;

use App\Enums\EmailEditor;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teams\SaveTeamEmailSettingsRequest;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TeamEmailSettingsController extends Controller
{
    public function edit(Request $request, Team $team): Response
    {
        Gate::authorize('update', $team);

        return Inertia::render('teams/email', [
            'team' => [
                'id' => $team->id,
                'uuid' => $team->uuid,
                'name' => $team->name,
                'slug' => $team->slug,
                'logo' => $team->logo,
                'isPersonal' => $team->is_personal,
            ],
            'settings' => [
                'email_editor' => $team->email_editor->value,
            ],
            'editors' => EmailEditor::options(),
            'permissions' => $request->user()->toTeamPermissions($team),
        ]);
    }

    public function update(SaveTeamEmailSettingsRequest $request, Team $team): RedirectResponse
    {
        $team->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Email builder updated.')]);

        return back();
    }
}
