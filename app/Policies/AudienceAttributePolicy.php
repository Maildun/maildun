<?php

namespace App\Policies;

use App\Enums\TeamPermission;
use App\Models\Audience;
use App\Models\AudienceAttribute;
use App\Models\User;

class AudienceAttributePolicy
{
    public function create(User $user, Audience $audience): bool
    {
        return $user->hasTeamPermission($audience->team, TeamPermission::ManageAudience);
    }

    public function update(User $user, AudienceAttribute $audienceAttribute): bool
    {
        return $user->hasTeamPermission($audienceAttribute->audience->team, TeamPermission::ManageAudience);
    }

    public function delete(User $user, AudienceAttribute $audienceAttribute): bool
    {
        return $this->update($user, $audienceAttribute);
    }
}
