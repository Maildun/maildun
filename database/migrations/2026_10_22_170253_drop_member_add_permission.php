<?php

use App\Enums\TeamRole;
use App\Models\WorkspaceRole;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * The removed permission: members only join through invitations, so it never gated anything.
     */
    private const MEMBER_ADD = 'member:add';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        // The FKs on role_has_permissions and model_has_permissions cascade on
        // delete, so this also strips it from every system and custom role.
        Permission::where('name', self::MEMBER_ADD)->where('guard_name', 'web')->first()?->delete();

        $registrar->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        $memberAdd = Permission::findOrCreate(self::MEMBER_ADD, 'web');

        WorkspaceRole::query()
            ->whereIn('name', [TeamRole::Owner->value, TeamRole::Admin->value])
            ->where('is_system', true)
            ->each(fn (WorkspaceRole $workspaceRole) => $workspaceRole->givePermissionTo($memberAdd));

        $registrar->forgetCachedPermissions();
    }
};
