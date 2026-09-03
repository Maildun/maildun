<?php

namespace App\Policies;

use App\Enums\TeamPermission;
use App\Models\Audience;
use App\Models\SubscribeForm;
use App\Models\User;

class SubscribeFormPolicy
{
    public function view(User $user, SubscribeForm $subscribeForm): bool
    {
        return $user->belongsToTeam($subscribeForm->audience->team);
    }

    public function create(User $user, Audience $audience): bool
    {
        return $user->hasTeamPermission($audience->team, TeamPermission::ManageAudience);
    }

    public function update(User $user, SubscribeForm $subscribeForm): bool
    {
        return $user->hasTeamPermission($subscribeForm->audience->team, TeamPermission::ManageAudience);
    }

    public function delete(User $user, SubscribeForm $subscribeForm): bool
    {
        return $this->update($user, $subscribeForm);
    }
}
