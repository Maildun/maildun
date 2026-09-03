<?php

namespace App\Policies;

use App\Enums\TeamPermission;
use App\Models\Audience;
use App\Models\Team;
use App\Models\User;

class AudiencePolicy
{
    public function view(User $user, Audience $audience): bool
    {
        return $user->belongsToTeam($audience->team);
    }

    public function create(User $user, Team $team): bool
    {
        return $user->hasTeamPermission($team, TeamPermission::ManageAudience);
    }

    public function update(User $user, Audience $audience): bool
    {
        return $user->hasTeamPermission($audience->team, TeamPermission::ManageAudience);
    }

    public function delete(User $user, Audience $audience): bool
    {
        return $this->update($user, $audience);
    }
}
