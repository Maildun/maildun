<?php

namespace App\Policies;

use App\Enums\TeamPermission;
use App\Models\CampaignSeries;
use App\Models\Team;
use App\Models\User;

class CampaignSeriesPolicy
{
    public function viewAny(User $user, Team $team): bool
    {
        return $user->belongsToTeam($team);
    }

    public function view(User $user, CampaignSeries $campaignSeries): bool
    {
        return $user->belongsToTeam($campaignSeries->team);
    }

    public function create(User $user, Team $team): bool
    {
        return $user->hasTeamPermission($team, TeamPermission::ManageCampaign);
    }

    public function update(User $user, CampaignSeries $campaignSeries): bool
    {
        return $user->hasTeamPermission($campaignSeries->team, TeamPermission::ManageCampaign);
    }

    public function delete(User $user, CampaignSeries $campaignSeries): bool
    {
        return $this->update($user, $campaignSeries);
    }
}
