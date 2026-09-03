<?php

namespace App\Policies;

use App\Enums\TeamPermission;
use App\Models\Email;
use App\Models\Team;
use App\Models\User;

class EmailPolicy
{
    public function viewAny(User $user, Team $team): bool
    {
        return $user->belongsToTeam($team);
    }

    public function view(User $user, Email $email): bool
    {
        return $user->belongsToTeam($email->team);
    }

    public function create(User $user, Team $team): bool
    {
        return $user->hasTeamPermission($team, TeamPermission::ManageCampaign);
    }

    public function update(User $user, Email $email): bool
    {
        return $user->hasTeamPermission($email->team, TeamPermission::ManageCampaign);
    }

    public function delete(User $user, Email $email): bool
    {
        return $this->update($user, $email);
    }

    /**
     * Sending a test copy is a write-level action: it puts the draft in front
     * of a real inbox using the team's sender identity.
     */
    public function sendTest(User $user, Email $email): bool
    {
        return $this->update($user, $email);
    }

    public function send(User $user, Email $email): bool
    {
        return $this->update($user, $email);
    }
}
