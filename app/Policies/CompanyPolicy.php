<?php

namespace App\Policies;

use App\Enums\TeamPermission;
use App\Models\Company;
use App\Models\Team;
use App\Models\User;

class CompanyPolicy
{
    public function viewAny(User $user, Team $team): bool
    {
        return $user->belongsToTeam($team);
    }

    public function view(User $user, Company $company): bool
    {
        return $user->belongsToTeam($company->team);
    }

    public function create(User $user, Team $team): bool
    {
        return $user->hasTeamPermission($team, TeamPermission::ManageCompany);
    }

    public function update(User $user, Company $company): bool
    {
        return $user->hasTeamPermission($company->team, TeamPermission::ManageCompany);
    }

    public function delete(User $user, Company $company): bool
    {
        return $this->update($user, $company);
    }
}
