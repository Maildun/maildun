<?php

namespace App\Http\Controllers\Teams;

use App\Enums\TeamPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teams\StoreWorkspaceRoleRequest;
use App\Http\Requests\Teams\UpdateWorkspaceRoleRequest;
use App\Models\Team;
use App\Models\WorkspaceRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;

class TeamRoleController extends Controller
{
    /**
     * Display the workspace's roles and their permission sets.
     */
    public function index(Team $team): Response
    {
        Gate::authorize('manageRoles', $team);

        $team->ensureDefaultRoles();

        return Inertia::render('teams/roles', [
            'team' => [
                'id' => $team->id,
                'uuid' => $team->uuid,
                'name' => $team->name,
                'slug' => $team->slug,
                'logo' => $team->logo,
                'isPersonal' => $team->is_personal,
            ],
            'roles' => $team->workspaceRoles()
                ->with('permissions:id,name')
                ->get()
                ->sortBy(fn (WorkspaceRole $role): array => [
                    match ($role->name) {
                        'owner' => 0,
                        'admin' => 1,
                        'member' => 2,
                        default => 3,
                    },
                    $role->label,
                ])
                ->values()
                ->map(fn (WorkspaceRole $role): array => $this->roleProps($role)),
            'permissions' => collect(TeamPermission::cases())
                ->map(fn (TeamPermission $permission): array => [
                    'value' => $permission->value,
                    'label' => str($permission->value)->replace(':', ' ')->headline()->toString(),
                ]),
        ]);
    }

    /**
     * Create a workspace-scoped custom role.
     */
    public function store(StoreWorkspaceRoleRequest $request, Team $team): RedirectResponse
    {
        Gate::authorize('manageRoles', $team);

        $role = $team->workspaceRoles()->create([
            'name' => WorkspaceRole::nameFromLabel($request->validated('name')),
            'label' => $request->validated('name'),
            'guard_name' => 'web',
            'is_system' => false,
        ]);

        $role->syncPermissions($request->validated('permissions', []));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role created.')]);

        return to_route('teams.roles.index', ['team' => $team->slug]);
    }

    /**
     * Update a role label and its permission set.
     */
    public function update(UpdateWorkspaceRoleRequest $request, Team $team, WorkspaceRole $workspaceRole): RedirectResponse
    {
        Gate::authorize('manageRoles', $team);
        abort_if($workspaceRole->isOwner(), 403, __('The workspace owner role is managed by the system.'));

        $workspaceRole->update(['label' => $request->validated('name')]);
        $workspaceRole->syncPermissions($request->validated('permissions', []));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role updated.')]);

        return to_route('teams.roles.index', ['team' => $team->slug]);
    }

    /**
     * Remove an unassigned custom role.
     */
    public function destroy(Team $team, WorkspaceRole $workspaceRole): RedirectResponse
    {
        Gate::authorize('manageRoles', $team);
        abort_if($workspaceRole->is_system, 403, __('System roles cannot be deleted.'));
        abort_if(
            $team->memberships()->where('role', $workspaceRole->name)->exists()
                || $team->invitations()->where('role', $workspaceRole->name)->exists(),
            422,
            __('Assign members and invitations to another role before deleting this role.'),
        );

        $workspaceRole->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role deleted.')]);

        return to_route('teams.roles.index', ['team' => $team->slug]);
    }

    /**
     * Serialize a workspace role for the settings page.
     *
     * @return array{id: int, name: string, label: string, is_system: bool, is_owner: bool, permissions: list<string>}
     */
    private function roleProps(WorkspaceRole $role): array
    {
        return [
            'id' => $role->id,
            'name' => $role->name,
            'label' => $role->label,
            'is_system' => $role->is_system,
            'is_owner' => $role->isOwner(),
            'permissions' => array_values($role->permissions
                ->map(fn (Permission $permission): string => $permission->name)
                ->all()),
        ];
    }
}
