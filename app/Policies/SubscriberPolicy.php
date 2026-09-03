<?php

namespace App\Policies;

use App\Enums\TeamPermission;
use App\Models\Audience;
use App\Models\Subscriber;
use App\Models\User;

class SubscriberPolicy
{
    public function view(User $user, Subscriber $subscriber): bool
    {
        return $user->belongsToTeam($subscriber->audience->team);
    }

    public function create(User $user, Audience $audience): bool
    {
        return $user->hasTeamPermission($audience->team, TeamPermission::ManageAudience);
    }

    public function update(User $user, Subscriber $subscriber): bool
    {
        return $user->hasTeamPermission($subscriber->audience->team, TeamPermission::ManageAudience);
    }

    public function delete(User $user, Subscriber $subscriber): bool
    {
        return $this->update($user, $subscriber);
    }
}
