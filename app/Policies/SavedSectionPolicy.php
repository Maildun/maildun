<?php

namespace App\Policies;

use App\Enums\TeamPermission;
use App\Models\SavedSection;
use App\Models\Team;
use App\Models\User;

class SavedSectionPolicy
{
    public function viewAny(User $user, Team $team): bool
    {
        return $user->belongsToTeam($team);
    }

    /**
     * Saved sections are shared by every block editor (campaigns,
     * transactional emails and templates), so anyone who edits one may save.
     */
    public function create(User $user, Team $team): bool
    {
        return $user->hasTeamPermission($team, TeamPermission::ManageCampaign)
            || $user->hasTeamPermission($team, TeamPermission::ManageTransactional)
            || $user->hasTeamPermission($team, TeamPermission::ManageTemplate);
    }

    public function update(User $user, SavedSection $savedSection): bool
    {
        return $this->create($user, $savedSection->team);
    }

    public function delete(User $user, SavedSection $savedSection): bool
    {
        return $this->update($user, $savedSection);
    }
}
