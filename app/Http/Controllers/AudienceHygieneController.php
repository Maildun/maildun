<?php

namespace App\Http\Controllers;

use App\Actions\Audiences\ManageAudienceHygiene;
use App\Http\Requests\AudienceHygieneCleanupRequest;
use App\Models\Audience;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AudienceHygieneController extends Controller
{
    public function index(Team $currentTeam, Audience $audience, ManageAudienceHygiene $hygiene): Response
    {
        Gate::authorize('update', $audience);

        return Inertia::render('audiences/settings/hygiene', [
            'audience' => [
                'uuid' => $audience->uuid,
                'name' => $audience->name,
                'avatar' => $audience->avatar,
            ],
            'counts' => $hygiene->handle($audience),
            // Sent back with a cleanup so it removes only the counted set.
            'countedAt' => now()->toISOString(),
        ]);
    }

    public function destroyUnconfirmed(
        AudienceHygieneCleanupRequest $request,
        Team $currentTeam,
        Audience $audience,
        ManageAudienceHygiene $hygiene,
    ): RedirectResponse {
        $deleted = $hygiene->deleteUnconfirmed($audience, $request->countedAt(), $request->confirmedCount());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Removed :count unconfirmed subscriber(s).', ['count' => $deleted]),
        ]);

        return back();
    }

    public function destroyInactive(
        AudienceHygieneCleanupRequest $request,
        Team $currentTeam,
        Audience $audience,
        ManageAudienceHygiene $hygiene,
    ): RedirectResponse {
        $deleted = $hygiene->deleteInactive($audience, $request->countedAt(), $request->confirmedCount());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Removed :count inactive subscriber(s).', ['count' => $deleted]),
        ]);

        return back();
    }
}
