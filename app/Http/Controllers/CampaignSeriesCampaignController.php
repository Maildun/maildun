<?php

namespace App\Http\Controllers;

use App\Http\Requests\AddCampaignsToSeriesRequest;
use App\Models\CampaignSeries;
use App\Models\Email;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class CampaignSeriesCampaignController extends Controller
{
    public function store(
        AddCampaignsToSeriesRequest $request,
        Team $currentTeam,
        CampaignSeries $campaignSeries,
    ): RedirectResponse {
        $campaignUuids = $request->validated('campaign_uuids');

        $currentTeam->emails()
            ->whereNull('campaign_series_id')
            ->whereIn('uuid', $campaignUuids)
            ->update(['campaign_series_id' => $campaignSeries->id]);

        $campaignSeries->touch();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Campaigns added to the series.')]);

        return back();
    }

    public function destroy(
        Team $currentTeam,
        CampaignSeries $campaignSeries,
        Email $email,
    ): RedirectResponse {
        Gate::authorize('update', $campaignSeries);
        abort_unless($email->campaign_series_id === $campaignSeries->id, 404);

        $email->update(['campaign_series_id' => null]);
        $campaignSeries->touch();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Campaign removed from the series.')]);

        return back();
    }
}
