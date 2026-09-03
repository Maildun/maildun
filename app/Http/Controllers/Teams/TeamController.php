<?php

namespace App\Http\Controllers\Teams;

use App\Actions\Teams\CreateTeam;
use App\Actions\Teams\ResolveWorkspaceSwitchDestination;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teams\DeleteTeamRequest;
use App\Http\Requests\Teams\SaveTeamRequest;
use App\Models\Membership;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class TeamController extends Controller
{
    /**
     * Redirect to the current team's settings page.
     */
    public function index(Request $request): RedirectResponse
    {
        return $this->redirectToTeamSettings($request->user()->currentTeam);
    }

    /**
     * Show the workspace creation page.
     */
    public function create(): Response
    {
        return Inertia::render('teams/create');
    }

    /**
     * Store a newly created team.
     */
    public function store(SaveTeamRequest $request, CreateTeam $createTeam): RedirectResponse
    {
        $team = $createTeam->handle(
            $request->user(),
            $request->validated('name'),
            $request->file('logo'),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Workspace created.')]);

        return to_route('teams.edit', ['team' => $team->slug]);
    }

    /**
     * Show the team edit page.
     */
    public function edit(Request $request, Team $team): Response
    {
        Gate::authorize('update', $team);

        $user = $request->user();

        return Inertia::render('teams/edit', [
            'team' => [
                'id' => $team->id,
                'uuid' => $team->uuid,
                'name' => $team->name,
                'slug' => $team->slug,
                'logo' => $team->logo,
                'isPersonal' => $team->is_personal,
            ],
            'permissions' => $user->toTeamPermissions($team),
        ]);
    }

    /**
     * Update the specified team.
     */
    public function update(SaveTeamRequest $request, Team $team): RedirectResponse
    {
        Gate::authorize('update', $team);

        $oldLogoPath = null;

        $team = DB::transaction(function () use ($request, $team, &$oldLogoPath) {
            $team = Team::whereKey($team->id)->lockForUpdate()->firstOrFail();

            $team->name = $request->validated('name');

            if ($request->hasFile('logo')) {
                $storedPath = $request->file('logo')->store('team-logos', 'public');

                if ($storedPath === false) {
                    throw new RuntimeException('Unable to store the uploaded team logo.');
                }

                $oldLogoPath = $team->getRawOriginal('logo_path');
                $team->logo_path = $storedPath;
            }

            $team->save();

            return $team;
        });

        if ($oldLogoPath) {
            Storage::disk('public')->delete($oldLogoPath);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Workspace updated.')]);

        return to_route('teams.edit', ['team' => $team->slug]);
    }

    /**
     * Switch the user's current team.
     */
    public function switch(Request $request, Team $team, ResolveWorkspaceSwitchDestination $destination): RedirectResponse
    {
        abort_unless($request->user()->belongsToTeam($team), 403);

        $previousTeam = $request->user()->currentTeam;

        $request->user()->switchTeam($team);

        return redirect()->to($destination->handle($request, $team, $previousTeam));
    }

    /**
     * Leave the specified team.
     */
    public function leave(Request $request, Team $team): RedirectResponse
    {
        Gate::authorize('leave', $team);

        $user = $request->user();

        $fallbackTeam = $user->isCurrentTeam($team)
            ? $user->fallbackTeam($team)
            : null;

        $team->memberships()
            ->where('user_id', $user->id)
            ->firstOrFail()
            ->delete();

        if ($fallbackTeam) {
            $user->switchTeam($fallbackTeam);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('You left the workspace ":name"', ['name' => $team->name])]);

        return $this->redirectToTeamSettings($fallbackTeam ?? $user->fresh()->currentTeam);
    }

    /**
     * Delete the specified team.
     */
    public function destroy(DeleteTeamRequest $request, Team $team): RedirectResponse
    {
        $user = $request->user();
        $fallbackTeam = $user->isCurrentTeam($team)
            ? $user->fallbackTeam($team)
            : null;

        DB::transaction(function () use ($user, $team) {
            User::where('current_team_id', $team->id)
                ->where('id', '!=', $user->id)
                ->each(fn (User $affectedUser) => $affectedUser->switchTeam($affectedUser->personalTeam()));

            $team->invitations()->delete();
            $team->memberships()
                ->get()
                ->each(fn (Membership $membership) => $membership->delete());
            $team->emailIntegration()->delete();
            $team->delete();
        });

        if ($fallbackTeam) {
            $user->switchTeam($fallbackTeam);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Workspace deleted.')]);

        return $this->redirectToTeamSettings($fallbackTeam ?? $user->fresh()->currentTeam);
    }

    private function redirectToTeamSettings(?Team $team): RedirectResponse
    {
        abort_if($team === null, 404);

        return to_route('teams.edit', ['team' => $team->slug]);
    }
}
