<?php

namespace App\Policies;

use App\Enums\TeamPermission;
use App\Models\Team;
use App\Models\TransactionalEmail;
use App\Models\User;

class TransactionalEmailPolicy
{
    public function viewAny(User $user, Team $team): bool
    {
        return $user->belongsToTeam($team);
    }

    public function view(User $user, TransactionalEmail $transactionalEmail): bool
    {
        return $user->belongsToTeam($transactionalEmail->team);
    }

    public function create(User $user, Team $team): bool
    {
        return $user->hasTeamPermission($team, TeamPermission::ManageTransactional);
    }

    public function update(User $user, TransactionalEmail $transactionalEmail): bool
    {
        return $user->hasTeamPermission($transactionalEmail->team, TeamPermission::ManageTransactional);
    }

    public function delete(User $user, TransactionalEmail $transactionalEmail): bool
    {
        return $this->update($user, $transactionalEmail);
    }

    public function sendTest(User $user, TransactionalEmail $transactionalEmail): bool
    {
        return $this->update($user, $transactionalEmail);
    }

    public function publish(User $user, TransactionalEmail $transactionalEmail): bool
    {
        return $this->update($user, $transactionalEmail);
    }
}
