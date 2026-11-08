<?php

use App\Enums\TeamPermission;
use App\Enums\TeamRole;
use App\Models\WorkspaceRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Admins keep member management, but only the owner deletes a workspace.
     * The admin role is a stored, per-workspace copy, so changing TeamRole
     * only reaches workspaces created from now on.
     */
    public function up(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        $deleteTeam = Permission::findOrCreate(TeamPermission::DeleteTeam->value, 'web');

        $this->systemAdminRoles()->each(
            fn (WorkspaceRole $workspaceRole) => $workspaceRole->revokePermissionTo($deleteTeam),
        );

        $registrar->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        $deleteTeam = Permission::findOrCreate(TeamPermission::DeleteTeam->value, 'web');

        $this->systemAdminRoles()->each(
            fn (WorkspaceRole $workspaceRole) => $workspaceRole->givePermissionTo($deleteTeam),
        );

        $registrar->forgetCachedPermissions();
    }

    /**
     * The built-in admin role of every workspace.
     *
     * @return Builder<WorkspaceRole>
     */
    private function systemAdminRoles(): Builder
    {
        return WorkspaceRole::query()
            ->where('name', TeamRole::Admin->value)
            ->where('is_system', true);
    }
};
