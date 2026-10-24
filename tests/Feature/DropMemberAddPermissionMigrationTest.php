<?php

use App\Models\Team;
use Spatie\Permission\Models\Permission;

test('strips member:add from stored roles and keeps their other permissions', function () {
    $team = Team::factory()->create();
    $role = $team->workspaceRoles()->create([
        'name' => 'custom-recruiter',
        'label' => 'Recruiter',
        'guard_name' => 'web',
        'is_system' => false,
    ]);
    $role->givePermissionTo([
        Permission::findOrCreate('member:add', 'web'),
        Permission::findOrCreate('invitation:create', 'web'),
    ]);

    (require base_path('database/migrations/2026_10_22_170253_drop_member_add_permission.php'))->up();

    expect(Permission::where('name', 'member:add')->exists())->toBeFalse()
        ->and($role->fresh()->permissions->pluck('name')->all())->toBe(['invitation:create']);
});
