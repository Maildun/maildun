<?php

namespace App\Models;

use App\Enums\TeamPermission;
use App\Enums\TeamRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Spatie\Permission\Contracts\Permission as PermissionContract;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * A permission role scoped to one workspace.
 *
 * The stable `name` is used by memberships and Spatie. The mutable `label`
 * is displayed to workspace owners.
 *
 * @property int $id
 * @property int $team_id
 * @property string $label
 * @property bool $is_system
 */
class WorkspaceRole extends Role
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }

    /**
     * Get the workspace that owns this role.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Scope roles to one workspace.
     *
     * @param  Builder<WorkspaceRole>  $query
     * @return Builder<WorkspaceRole>
     */
    public function scopeForTeam(Builder $query, Team $team): Builder
    {
        return $query->where('team_id', $team->id);
    }

    /**
     * Determine whether this is the protected owner role.
     */
    public function isOwner(): bool
    {
        return $this->name === TeamRole::Owner->value;
    }

    /**
     * Determine whether this role may be assigned to members and invitations.
     */
    public function isAssignable(): bool
    {
        return ! $this->isOwner();
    }

    /**
     * Create a stable, human-readable role identifier.
     */
    public static function nameFromLabel(string $label): string
    {
        return Str::slug($label);
    }

    /**
     * Get the permissions assigned by default to a system role.
     *
     * @return array<PermissionContract>
     */
    public static function defaultPermissions(TeamRole $role): array
    {
        return collect($role->permissions())
            ->map(fn (TeamPermission $permission) => Permission::findOrCreate($permission->value, 'web'))
            ->all();
    }
}
