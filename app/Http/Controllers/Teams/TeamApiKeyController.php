<?php

namespace App\Http\Controllers\Teams;

use App\Http\Controllers\Controller;
use App\Http\Requests\Teams\StoreTeamApiKeyRequest;
use App\Models\Team;
use App\Models\TeamApiKey;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TeamApiKeyController extends Controller
{
    public function index(Request $request, Team $team): Response
    {
        Gate::authorize('update', $team);

        return Inertia::render('teams/api', [
            'team' => [
                'id' => $team->id,
                'uuid' => $team->uuid,
                'name' => $team->name,
                'slug' => $team->slug,
                'logo' => $team->logo,
                'isPersonal' => $team->is_personal,
            ],
            'apiKeys' => $team->apiKeys()
                ->latest()
                ->get()
                ->map(fn (TeamApiKey $apiKey): array => [
                    'uuid' => $apiKey->uuid,
                    'name' => $apiKey->name,
                    'prefix' => TeamApiKey::TOKEN_PREFIX.$apiKey->prefix.'_••••••••',
                    'last_used_at' => $apiKey->last_used_at?->toISOString(),
                    'created_at' => $apiKey->created_at?->toISOString(),
                ]),
            'apiBaseUrl' => url('/api/v1'),
            'canManage' => Gate::allows('update', $team),
        ]);
    }

    public function store(StoreTeamApiKeyRequest $request, Team $team): RedirectResponse
    {
        $issued = TeamApiKey::issue($team, $request->string('name')->value());

        Inertia::flash('apiKey', [
            'name' => $issued['key']->name,
            'token' => $issued['token'],
        ]);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('API key created.')]);

        return back();
    }

    public function destroy(Team $team, TeamApiKey $teamApiKey): RedirectResponse
    {
        Gate::authorize('update', $team);
        abort_unless($teamApiKey->team_id === $team->id, 404);

        $teamApiKey->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('API key revoked.')]);

        return back();
    }
}
