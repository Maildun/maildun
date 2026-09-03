<?php

namespace App\Http\Controllers;

use App\Actions\Audiences\ManageAudienceHygiene;
use App\Http\Requests\BulkListHygieneRequest;
use App\Models\Audience;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ListHygieneController extends Controller
{
    public function index(
        Request $request,
        Team $currentTeam,
        ManageAudienceHygiene $hygiene,
    ): Response {
        $filters = $this->filters($request, $currentTeam);

        return Inertia::render('list-hygiene/index', [
            'audiences' => $hygiene->forTeam($currentTeam),
            'subscribers' => $hygiene->paginateForTeam(
                $currentTeam,
                $filters['kind'],
                $filters['audience'],
                $filters['search'],
            ),
            'filters' => $filters,
            'canManage' => Gate::allows('create', [Audience::class, $currentTeam]),
        ]);
    }

    public function destroyUnconfirmed(
        Team $currentTeam,
        BulkListHygieneRequest $request,
        ManageAudienceHygiene $hygiene,
    ): RedirectResponse {
        $deleted = $hygiene->deleteTeamUnconfirmed(
            $currentTeam,
            $request->subscriberUuids(),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Removed :count unconfirmed subscriber(s).', ['count' => $deleted]),
        ]);

        return back();
    }

    public function destroyInactive(
        Team $currentTeam,
        BulkListHygieneRequest $request,
        ManageAudienceHygiene $hygiene,
    ): RedirectResponse {
        $deleted = $hygiene->deleteTeamInactive(
            $currentTeam,
            $request->subscriberUuids(),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Removed :count inactive subscriber(s).', ['count' => $deleted]),
        ]);

        return back();
    }

    /** @return array{kind: string, audience: string, search: string} */
    private function filters(Request $request, Team $team): array
    {
        $kind = $request->string('kind')->toString();
        $audience = $request->string('audience')->toString();

        return [
            'kind' => in_array($kind, ['unconfirmed', 'inactive'], true)
                ? $kind
                : 'unconfirmed',
            'audience' => $audience !== ''
                && $audience !== 'all'
                && $team->audiences()->where('uuid', $audience)->exists()
                    ? $audience
                    : 'all',
            'search' => $request->string('search')->trim()->toString(),
        ];
    }
}
