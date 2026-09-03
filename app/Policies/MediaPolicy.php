<?php

namespace App\Policies;

use App\Enums\TeamPermission;
use App\Models\Media;
use App\Models\Team;
use App\Models\User;

class MediaPolicy
{
    public function viewAny(User $user, Team $team): bool
    {
        return $user->belongsToTeam($team);
    }

    public function view(User $user, Media $media): bool
    {
        return $user->belongsToTeam($media->team);
    }

    public function create(User $user, Team $team): bool
    {
        return $user->hasTeamPermission($team, TeamPermission::ManageMedia);
    }

    public function update(User $user, Media $media): bool
    {
        return $user->hasTeamPermission($media->team, TeamPermission::ManageMedia);
    }

    public function delete(User $user, Media $media): bool
    {
        return $this->update($user, $media);
    }

    public function updateSettings(User $user, Team $team): bool
    {
        return $user->hasTeamPermission($team, TeamPermission::ManageMedia);
    }
}
