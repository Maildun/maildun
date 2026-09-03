<?php

namespace App\Policies;

use App\Enums\TeamPermission;
use App\Models\Automation;
use App\Models\Team;
use App\Models\User;

class AutomationPolicy
{
    public function viewAny(User $user, Team $team): bool
    {
        return $user->belongsToTeam($team);
    }

    public function view(User $user, Automation $automation): bool
    {
        return $user->belongsToTeam($automation->team);
    }

    public function create(User $user, Team $team): bool
    {
        return $user->hasTeamPermission($team, TeamPermission::ManageAutomation);
    }

    public function update(User $user, Automation $automation): bool
    {
        return $user->hasTeamPermission($automation->team, TeamPermission::ManageAutomation);
    }

    public function delete(User $user, Automation $automation): bool
    {
        return $this->update($user, $automation);
    }

    public function activate(User $user, Automation $automation): bool
    {
        return $this->update($user, $automation);
    }

    public function pause(User $user, Automation $automation): bool
    {
        return $this->update($user, $automation);
    }
}
