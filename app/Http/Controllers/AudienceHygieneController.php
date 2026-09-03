<?php

namespace App\Http\Controllers;

use App\Actions\Audiences\ManageAudienceHygiene;
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
        ]);
    }

    public function destroyUnconfirmed(
        Team $currentTeam,
        Audience $audience,
        ManageAudienceHygiene $hygiene,
    ): RedirectResponse {
        Gate::authorize('update', $audience);

        $deleted = $hygiene->deleteUnconfirmed($audience);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Removed :count unconfirmed subscriber(s).', ['count' => $deleted]),
        ]);

        return back();
    }

    public function destroyInactive(
        Team $currentTeam,
        Audience $audience,
        ManageAudienceHygiene $hygiene,
    ): RedirectResponse {
        Gate::authorize('update', $audience);

        $deleted = $hygiene->deleteInactive($audience);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Removed :count inactive subscriber(s).', ['count' => $deleted]),
        ]);

        return back();
    }
}
