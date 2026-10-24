<?php

namespace App\Policies;

use App\Enums\TeamPermission;
use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class TeamPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Team $team): bool
    {
        return $user->belongsToTeam($team);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Team $team): bool
    {
        return $user->hasTeamPermission($team, TeamPermission::UpdateTeam);
    }

    /**
     * Determine whether the user can leave the team.
     */
    public function leave(User $user, Team $team): bool
    {
        return ! $team->is_personal
            && $user->belongsToTeam($team)
            && ! $user->ownsTeam($team);
    }

    /**
     * Determine whether the user can update a member's role in the team.
     */
    public function updateMember(User $user, Team $team): bool
    {
        return $user->hasTeamPermission($team, TeamPermission::UpdateMember);
    }

    /**
     * Determine whether the user can set or reset a member's password from the team.
     *
     * A password unlocks every workspace the member belongs to or has a pending invitation to,
     * so the user must be able to manage the member in each of them. Every account starts with
     * a personal workspace, so one the member still uses alone and has left empty is exempt.
     */
    public function manageMemberPassword(User $user, Team $team, User $member): Response
    {
        if (! $this->updateMember($user, $team)) {
            return Response::deny();
        }

        $member->loadMissing(['teamMemberships.team' => fn ($query) => $query
            ->withCount('members')
            ->withExists(Team::CONTENT_RELATIONS)]);

        foreach ($member->teamMemberships as $membership) {
            $memberTeam = $membership->team;
            $ownsMemberTeam = $membership->role === TeamRole::Owner->value;

            if ($memberTeam->is($team)) {
                if ($ownsMemberTeam) {
                    return Response::deny(__('The team owner password cannot be managed.'));
                }

                continue;
            }

            if ($member->is($user) || $this->isUntouchedPersonalTeam($memberTeam)) {
                continue;
            }

            if ($ownsMemberTeam || ! $user->hasTeamPermission($memberTeam, TeamPermission::UpdateMember)) {
                return Response::deny(__('This member also belongs to a workspace you cannot manage, so only they can change their password.'));
            }
        }

        if ($member->is($user)) {
            return Response::allow();
        }

        $invitedTeams = TeamInvitation::query()
            ->with('team')
            ->whereRaw('LOWER(email) = ?', [strtolower($member->email)])
            ->whereNull('accepted_at')
            ->where(fn ($query) => $query
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>=', now()))
            ->get()
            ->pluck('team')
            ->filter();

        foreach ($invitedTeams as $invitedTeam) {
            if (! $user->hasTeamPermission($invitedTeam, TeamPermission::UpdateMember)) {
                return Response::deny(__('This member has a pending invitation to a workspace you cannot manage, so only they can change their password.'));
            }
        }

        return Response::allow();
    }

    /**
     * Determine whether a workspace is the empty personal one its only member started with.
     */
    private function isUntouchedPersonalTeam(Team $team): bool
    {
        return $team->is_personal
            && (int) ($team->getAttribute('members_count') ?? $team->members()->count()) === 1
            && ! $team->hasContent();
    }

    /**
     * Determine whether the user can remove a member from the team.
     */
    public function removeMember(User $user, Team $team): bool
    {
        return $user->hasTeamPermission($team, TeamPermission::RemoveMember);
    }

    /**
     * Determine whether the user can invite members to the team.
     */
    public function inviteMember(User $user, Team $team): bool
    {
        return $user->hasTeamPermission($team, TeamPermission::CreateInvitation);
    }

    /**
     * Determine whether the user can cancel invitations.
     */
    public function cancelInvitation(User $user, Team $team): bool
    {
        return $user->hasTeamPermission($team, TeamPermission::CancelInvitation);
    }

    /**
     * Determine whether the user can manage workspace roles.
     */
    public function manageRoles(User $user, Team $team): bool
    {
        return $user->ownsTeam($team);
    }

    /**
     * Determine whether the user can view and run deployment diagnostics.
     */
    public function viewSystemCheck(User $user, Team $team): bool
    {
        return $user->ownsTeam($team);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Team $team): bool
    {
        return ! $team->is_personal && $user->hasTeamPermission($team, TeamPermission::DeleteTeam);
    }
}
