<?php

namespace Database\Seeders;

use App\Enums\TeamPermission;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * The baseline every deployment needs, demo data excluded.
 *
 * DatabaseSeeder fills a workspace with sample campaigns and subscribers, so it
 * must never run against a real install. This seeder is the production entry
 * point instead: it is idempotent and safe to re-run from a deploy script.
 */
class InstallSeeder extends Seeder
{
    public function run(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        /*
         * Roles are team scoped and created per workspace by
         * HasTeams::syncTeamRole. The permissions they point at are global, so
         * seeding them up front keeps the very first membership save from
         * being the thing that defines the application's permission set.
         */
        foreach (TeamPermission::cases() as $permission) {
            Permission::findOrCreate($permission->value, 'web');
        }

        $registrar->forgetCachedPermissions();

    }
}
