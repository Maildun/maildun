<?php

namespace App\Actions\Teams;

use App\Enums\TeamRole;
use App\Models\Membership;
use App\Models\Team;
use App\Models\User;

class ReleaseUserTeams
{
    /**
     * Detach a departing user from every team before their account is deleted.
     *
     * The team_members foreign key cascades in the database, which would drop
     * the pivot rows without firing Membership's deleted event and leave the
     * user's Spatie role assignments behind. Worse, a team whose only owner
     * disappears is unmanageable. So memberships are removed through the model,
     * and an owner hands the team to someone who can still run it.
     */
    public function handle(User $user): void
    {
        $user->loadMissing('teamMemberships');

        foreach ($user->teamMemberships as $membership) {
            // A soft deleted team is invisible to the relation, so read past the
            // scope: its memberships still have to be cleaned up.
            $team = $membership->team()->withTrashed()->first();

            if ($team === null || $team->trashed() || $membership->role !== TeamRole::Owner->value) {
                $membership->delete();

                continue;
            }

            $successor = $this->successor($team, $user);
            $membership->delete();

            $successor?->update(['role' => TeamRole::Owner->value]);

            // Nobody is left to inherit it, which is always the case for the
            // user's personal team, so the team goes with the account.
            if ($successor === null) {
                $team->emailIntegration()->delete();
                $team->delete();
            }
        }
    }

    /**
     * The longest standing admin, falling back to the longest standing member.
     */
    private function successor(Team $team, User $user): ?Membership
    {
        $candidates = $team->memberships()
            ->where('user_id', '!=', $user->id)
            ->oldest()
            ->get();

        return $candidates->firstWhere('role', TeamRole::Admin->value) ?? $candidates->first();
    }
}
