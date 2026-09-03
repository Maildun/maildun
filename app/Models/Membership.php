<?php

namespace App\Models;

use App\Enums\TeamRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property int $user_id
 * @property string $role
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 * @property-read User $user
 */
#[Fillable(['team_id', 'user_id', 'role'])]
class Membership extends Pivot
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'team_members';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = true;

    /**
     * Synchronize membership changes with Spatie's team-scoped roles.
     */
    protected static function booted(): void
    {
        static::saved(function (Membership $membership): void {
            $membership->team->ensureDefaultRoles();

            $role = $membership->team->workspaceRoles()
                ->where('name', $membership->role)
                ->firstOrFail();

            $membership->user->syncTeamRole($membership->team, $role);
        });

        static::deleted(function (Membership $membership): void {
            $membership->user->removeTeamRole($membership->team);
        });
    }

    /**
     * Get the team that the membership belongs to.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the user that belongs to this membership.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function setRoleAttribute(TeamRole|string $role): void
    {
        $this->attributes['role'] = $role instanceof TeamRole ? $role->value : $role;
    }
}
