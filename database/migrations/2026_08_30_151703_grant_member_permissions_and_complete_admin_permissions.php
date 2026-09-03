<?php

use App\Enums\TeamPermission;
use App\Enums\TeamRole;
use App\Models\WorkspaceRole;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        $this->grantPermissions(TeamRole::Member, $this->memberPermissions());
        $this->grantPermissions(TeamRole::Admin, TeamPermission::cases());

        $registrar->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        $this->revokePermissions(TeamRole::Member, $this->memberPermissions());
        $this->revokePermissions(TeamRole::Admin, [
            TeamPermission::DeleteTeam,
            TeamPermission::AddMember,
            TeamPermission::UpdateMember,
            TeamPermission::RemoveMember,
        ]);

        $registrar->forgetCachedPermissions();
    }

    /**
     * Get the permissions that members can access by default.
     *
     * @return list<TeamPermission>
     */
    private function memberPermissions(): array
    {
        return [
            TeamPermission::ManageContact,
            TeamPermission::ManageAudience,
            TeamPermission::ManageCompany,
            TeamPermission::ManageTag,
            TeamPermission::ManageCampaign,
            TeamPermission::ManageTransactional,
            TeamPermission::ManageAutomation,
            TeamPermission::ManageTemplate,
            TeamPermission::ManageMedia,
        ];
    }

    /**
     * Grant permissions to each system role with the given name.
     *
     * @param  list<TeamPermission>  $permissions
     */
    private function grantPermissions(TeamRole $role, array $permissions): void
    {
        $permissions = collect($permissions)
            ->map(fn (TeamPermission $permission) => Permission::findOrCreate($permission->value, 'web'));

        WorkspaceRole::query()
            ->where('name', $role->value)
            ->where('is_system', true)
            ->each(fn (WorkspaceRole $workspaceRole) => $workspaceRole->givePermissionTo($permissions));
    }

    /**
     * Revoke permissions from each system role with the given name.
     *
     * @param  list<TeamPermission>  $permissions
     */
    private function revokePermissions(TeamRole $role, array $permissions): void
    {
        $permissions = collect($permissions)
            ->map(fn (TeamPermission $permission) => Permission::findOrCreate($permission->value, 'web'));

        WorkspaceRole::query()
            ->where('name', $role->value)
            ->where('is_system', true)
            ->each(fn (WorkspaceRole $workspaceRole) => $workspaceRole->revokePermissionTo($permissions));
    }
};
