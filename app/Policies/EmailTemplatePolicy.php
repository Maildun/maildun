<?php

namespace App\Policies;

use App\Enums\TeamPermission;
use App\Models\EmailTemplate;
use App\Models\Team;
use App\Models\User;

class EmailTemplatePolicy
{
    public function viewAny(User $user, Team $team): bool
    {
        return $user->belongsToTeam($team);
    }

    public function create(User $user, Team $team): bool
    {
        return $user->hasTeamPermission($team, TeamPermission::ManageTemplate);
    }

    /**
     * Starter templates ship with the app, so nobody may edit them — teams
     * duplicate one into their own library instead.
     */
    public function update(User $user, EmailTemplate $emailTemplate): bool
    {
        if ($emailTemplate->isStarter() || $emailTemplate->team === null) {
            return false;
        }

        return $user->hasTeamPermission($emailTemplate->team, TeamPermission::ManageTemplate);
    }

    public function delete(User $user, EmailTemplate $emailTemplate): bool
    {
        return $this->update($user, $emailTemplate);
    }
}
