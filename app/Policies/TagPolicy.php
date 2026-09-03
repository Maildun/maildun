<?php

namespace App\Policies;

use App\Enums\TeamPermission;
use App\Models\Tag;
use App\Models\Team;
use App\Models\User;

class TagPolicy
{
    public function viewAny(User $user, Team $team): bool
    {
        return $user->belongsToTeam($team);
    }

    public function view(User $user, Tag $tag): bool
    {
        return $user->belongsToTeam($tag->team);
    }

    public function create(User $user, Team $team): bool
    {
        return $user->hasTeamPermission($team, TeamPermission::ManageTag);
    }

    public function update(User $user, Tag $tag): bool
    {
        return $user->hasTeamPermission($tag->team, TeamPermission::ManageTag);
    }

    public function delete(User $user, Tag $tag): bool
    {
        return $this->update($user, $tag);
    }
}
