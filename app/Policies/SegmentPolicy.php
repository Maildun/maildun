<?php

namespace App\Policies;

use App\Enums\TeamPermission;
use App\Models\Audience;
use App\Models\Segment;
use App\Models\User;

class SegmentPolicy
{
    public function view(User $user, Segment $segment): bool
    {
        return $user->belongsToTeam($segment->audience->team);
    }

    public function create(User $user, Audience $audience): bool
    {
        return $user->hasTeamPermission($audience->team, TeamPermission::ManageAudience);
    }

    public function update(User $user, Segment $segment): bool
    {
        return $user->hasTeamPermission($segment->audience->team, TeamPermission::ManageAudience);
    }

    public function delete(User $user, Segment $segment): bool
    {
        return $this->update($user, $segment);
    }
}
