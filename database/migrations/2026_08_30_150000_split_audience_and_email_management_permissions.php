<?php

use App\Models\WorkspaceRole;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Permissions split out of the old 'audience:manage' bucket.
     *
     * @var list<string>
     */
    private const AUDIENCE_SPLIT = ['contact:manage', 'company:manage', 'tag:manage'];

    /**
     * Permissions that replace the removed 'email:manage' bucket.
     *
     * @var list<string>
     */
    private const EMAIL_SPLIT = ['campaign:manage', 'automation:manage', 'transactional:manage', 'template:manage'];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        $audienceSplit = collect(self::AUDIENCE_SPLIT)
            ->map(fn (string $permission) => Permission::findOrCreate($permission, 'web'));

        $emailSplit = collect(self::EMAIL_SPLIT)
            ->map(fn (string $permission) => Permission::findOrCreate($permission, 'web'));

        WorkspaceRole::query()
            ->whereHas('permissions', fn ($query) => $query->where('name', 'audience:manage'))
            ->each(fn (WorkspaceRole $role) => $role->givePermissionTo($audienceSplit));

        WorkspaceRole::query()
            ->whereHas('permissions', fn ($query) => $query->where('name', 'email:manage'))
            ->each(fn (WorkspaceRole $role) => $role->givePermissionTo($emailSplit));

        // The FK on role_has_permissions.permission_id cascades on delete, so
        // this alone clears every role's now-obsolete 'email:manage' grant.
        Permission::where('name', 'email:manage')->where('guard_name', 'web')->first()?->delete();

        $registrar->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        $emailManage = Permission::findOrCreate('email:manage', 'web');

        WorkspaceRole::query()
            ->whereHas('permissions', fn ($query) => $query->whereIn('name', self::EMAIL_SPLIT), '=', count(self::EMAIL_SPLIT))
            ->each(fn (WorkspaceRole $role) => $role->givePermissionTo($emailManage));

        Permission::query()
            ->whereIn('name', [...self::AUDIENCE_SPLIT, ...self::EMAIL_SPLIT])
            ->where('guard_name', 'web')
            ->get()
            ->each(fn (Permission $permission) => $permission->delete());

        $registrar->forgetCachedPermissions();
    }
};
