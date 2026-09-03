<?php

namespace App\Concerns;

use App\Data\TeamPermissions;
use App\Data\UserTeam;
use App\Enums\TeamPermission;
use App\Enums\TeamRole;
use App\Models\Membership;
use App\Models\Team;
use App\Models\WorkspaceRole;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;

trait HasTeams
{
    /**
     * Get all of the teams the user belongs to.
     *
     * @return BelongsToMany<Team, $this>
     */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'team_members', 'user_id', 'team_id')
            ->withPivot(['role'])
            ->withTimestamps();
    }

    /**
     * Get all of the teams the user owns.
     *
     * @return HasManyThrough<Team, Membership, $this>
     */
    public function ownedTeams(): HasManyThrough
    {
        return $this->hasManyThrough(
            Team::class,
            Membership::class,
            'user_id',
            'id',
            'id',
            'team_id',
        )->where('team_members.role', TeamRole::Owner->value);
    }

    /**
     * Get all of the memberships for the user.
     *
     * @return HasMany<Membership, $this>
     */
    public function teamMemberships(): HasMany
    {
        return $this->hasMany(Membership::class, 'user_id');
    }

    /**
     * Get the user's current team.
     *
     * @return BelongsTo<Team, $this>
     */
    public function currentTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'current_team_id');
    }

    /**
     * Get the user's personal team.
     */
    public function personalTeam(): ?Team
    {
        return $this->teams()
            ->where('is_personal', true)
            ->first();
    }

    /**
     * Switch to the given team.
     */
    public function switchTeam(Team $team): bool
    {
        if (! $this->belongsToTeam($team)) {
            return false;
        }

        $this->update(['current_team_id' => $team->id]);
        $this->setRelation('currentTeam', $team);
        setPermissionsTeamId($team->getKey());

        URL::defaults(['current_team' => $team->slug]);

        return true;
    }

    /**
     * Determine if the user belongs to the given team.
     */
    public function belongsToTeam(Team $team): bool
    {
        return $this->teams()->where('teams.id', $team->id)->exists();
    }

    /**
     * Determine if the given team is the user's current team.
     */
    public function isCurrentTeam(Team $team): bool
    {
        return $this->current_team_id === $team->id;
    }

    /**
     * Determine if the user is the owner of the given team.
     */
    public function ownsTeam(Team $team): bool
    {
        return $this->teamRole($team) === TeamRole::Owner->value;
    }

    /**
     * Get the user's role on the given team.
     */
    public function teamRole(Team $team): ?string
    {
        return $this->teamMemberships()
            ->where('team_id', $team->id)
            ->first()
            ?->role;
    }

    /**
     * Get the user's teams as a collection of UserTeam objects.
     *
     * @return Collection<int, UserTeam>
     */
    public function toUserTeams(bool $includeCurrent = false): Collection
    {
        return $this->teams()
            ->get()
            ->map(fn (Team $team) => ! $includeCurrent && $this->isCurrentTeam($team) ? null : $this->toUserTeam($team))
            ->filter()
            ->values();
    }

    /**
     * Get the user's team as a UserTeam object.
     */
    public function toUserTeam(Team $team): UserTeam
    {
        $role = $this->teamRole($team);

        return new UserTeam(
            id: $team->id,
            uuid: $team->uuid,
            name: $team->name,
            slug: $team->slug,
            logo: $team->logo,
            isPersonal: $team->is_personal,
            role: $role,
            roleLabel: $role === null
                ? null
                : $team->workspaceRoles()->where('name', $role)->value('label') ?? str($role)->headline()->toString(),
            isCurrent: $this->isCurrentTeam($team),
            brandTheme: [
                'color' => $team->brand_color->value,
                'font' => $team->brand_font->value,
                'inputStyle' => $team->brand_input_style->value,
            ],
        );
    }

    /**
     * Get the standard permissions for a team as a TeamPermissions object.
     */
    public function toTeamPermissions(Team $team): TeamPermissions
    {
        return new TeamPermissions(
            canUpdateTeam: $this->hasTeamPermission($team, TeamPermission::UpdateTeam),
            canDeleteTeam: $this->hasTeamPermission($team, TeamPermission::DeleteTeam),
            canAddMember: $this->hasTeamPermission($team, TeamPermission::AddMember),
            canUpdateMember: $this->hasTeamPermission($team, TeamPermission::UpdateMember),
            canRemoveMember: $this->hasTeamPermission($team, TeamPermission::RemoveMember),
            canCreateInvitation: $this->hasTeamPermission($team, TeamPermission::CreateInvitation),
            canCancelInvitation: $this->hasTeamPermission($team, TeamPermission::CancelInvitation),
            canManageTags: $this->hasTeamPermission($team, TeamPermission::ManageTag),
            canManageEmails: $this->hasTeamPermission($team, TeamPermission::UpdateTeam),
            canLeaveTeam: $this->can('leave', $team),
        );
    }

    public function fallbackTeam(?Team $excluding = null): ?Team
    {
        return $this->teams()
            ->when($excluding, fn ($query) => $query->where('teams.id', '!=', $excluding->id))
            ->orderByRaw('LOWER(teams.name)')
            ->first();
    }

    /**
     * Determine if the user has the given permission on the team.
     */
    public function hasTeamPermission(Team $team, TeamPermission $permission): bool
    {
        if (! $this->belongsToTeam($team)) {
            return false;
        }

        setPermissionsTeamId($team->getKey());
        $this->unsetRelation('roles')->unsetRelation('permissions');

        return $this->checkPermissionTo($permission);
    }

    /**
     * Synchronize a membership role with Spatie's team-scoped role tables.
     */
    public function syncTeamRole(Team $team, WorkspaceRole $role): void
    {
        setPermissionsTeamId($team->getKey());

        $this->syncRoles($role);
        $this->unsetRelation('roles')->unsetRelation('permissions');
    }

    /**
     * Remove all Spatie role assignments for the user in the given team.
     */
    public function removeTeamRole(Team $team): void
    {
        setPermissionsTeamId($team->getKey());

        $this->syncRoles([]);
        $this->unsetRelation('roles')->unsetRelation('permissions');
    }
}
