<?php

use App\Enums\TeamRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Schema::table('roles', function (Blueprint $table) {
            $table->string('label')->nullable()->after('name');
            $table->boolean('is_system')->default(false)->after('guard_name');
        });

        $permissionIds = DB::table('permissions')->pluck('id', 'name');

        foreach (DB::table('teams')->orderBy('id')->pluck('id') as $teamId) {
            foreach (TeamRole::cases() as $role) {
                $roleId = DB::table('roles')
                    ->where('team_id', $teamId)
                    ->where('name', $role->value)
                    ->where('guard_name', 'web')
                    ->value('id');

                if ($roleId === null) {
                    $roleId = DB::table('roles')->insertGetId([
                        'team_id' => $teamId,
                        'name' => $role->value,
                        'label' => $role->label(),
                        'guard_name' => 'web',
                        'is_system' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } else {
                    DB::table('roles')->where('id', $roleId)->update([
                        'label' => $role->label(),
                        'is_system' => true,
                    ]);
                }

                foreach ($role->permissions() as $permission) {
                    $permissionId = $permissionIds[$permission->value] ?? null;

                    if ($permissionId !== null) {
                        DB::table('role_has_permissions')->insertOrIgnore([
                            'permission_id' => $permissionId,
                            'role_id' => $roleId,
                        ]);
                    }
                }
            }
        }

        DB::statement("CREATE UNIQUE INDEX team_members_one_owner ON team_members (team_id) WHERE role = 'owner'");

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        DB::statement('DROP INDEX IF EXISTS team_members_one_owner');

        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn(['label', 'is_system']);
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
