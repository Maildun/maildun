<?php

namespace App\Http\Controllers\Teams;

use App\Http\Controllers\Controller;
use App\Http\Requests\Teams\UpdateTeamMemberRequest;
use App\Models\Membership;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Models\WorkspaceRole;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Fortify;

class TeamMemberController extends Controller
{
    /** @var list<int> */
    private const PER_PAGE_OPTIONS = [10, 25, 50, 100];

    /**
     * Display the team's members and pending invitations.
     */
    public function index(Request $request, Team $team): Response
    {
        Gate::authorize('view', $team);

        $user = $request->user();
        $roleLabels = $team->workspaceRoles()->pluck('label', 'name');

        return Inertia::render('teams/members', [
            'team' => [
                'id' => $team->id,
                'uuid' => $team->uuid,
                'name' => $team->name,
                'slug' => $team->slug,
                'logo' => $team->logo,
                'isPersonal' => $team->is_personal,
            ],
            'members' => $team->members()
                ->orderByPivot('created_at')
                ->paginate($this->perPage($request, 'members_per_page'))
                ->withQueryString()
                ->through(fn (User $member): array => $this->memberProps($member, $roleLabels->get($member->pivot->role))),
            'invitations' => $team->invitations()
                ->whereNull('accepted_at')
                ->latest()
                ->paginate(
                    perPage: $this->perPage($request, 'invitations_per_page'),
                    pageName: 'invitations_page',
                )
                ->withQueryString()
                ->through(fn (TeamInvitation $invitation): array => [
                    'code' => $invitation->code,
                    'email' => $invitation->email,
                    'role' => $invitation->role,
                    'role_label' => $roleLabels->get($invitation->role) ?? str($invitation->role)->headline()->toString(),
                    'created_at' => $invitation->created_at->toISOString(),
                ]),
            'permissions' => $user->toTeamPermissions($team),
            'availableRoles' => $team->workspaceRoles()
                ->where('name', '!=', 'owner')
                ->orderBy('label')
                ->get(['name', 'label'])
                ->map(fn (WorkspaceRole $role): array => ['value' => $role->name, 'label' => $role->label]),
        ]);
    }

    private function perPage(Request $request, string $key): int
    {
        $perPage = $request->integer($key, 25);

        return in_array($perPage, self::PER_PAGE_OPTIONS, true) ? $perPage : 25;
    }

    /**
     * Serialize a team member for the settings page.
     *
     * @return array{id: int, uuid: string, name: string, email: string, avatar: string|null, role: string, role_label: string}
     */
    private function memberProps(User $member, ?string $roleLabel): array
    {
        /** @var Membership $membership */
        $membership = $member->getRelation('pivot');

        return [
            'id' => $member->id,
            'uuid' => $member->uuid,
            'name' => $member->name,
            'email' => $member->email,
            'avatar' => $member->avatar ?? null,
            'role' => $membership->role,
            'role_label' => $roleLabel ?? str($membership->role)->headline()->toString(),
        ];
    }

    /**
     * Update the specified team member's role.
     */
    public function update(UpdateTeamMemberRequest $request, Team $team, User $user): RedirectResponse
    {
        Gate::authorize('updateMember', $team);
        abort_if($team->owner()?->is($user), 403, __('The team owner role cannot be changed.'));

        $team->memberships()
            ->where('user_id', $user->id)
            ->firstOrFail()
            ->update(['role' => $request->validated('role')]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Member role updated.')]);

        return to_route('teams.members.index', ['team' => $team->slug]);
    }

    /**
     * Generate a new password for the specified team member.
     */
    public function generatePassword(Team $team, User $user): RedirectResponse
    {
        Gate::authorize('updateMember', $team);
        $this->ensureMemberPasswordCanBeManaged($team, $user);

        $password = $this->newPassword();

        $user->forceFill([
            'password' => $password,
            'remember_token' => Str::random(60),
        ])->save();

        Password::broker(config('fortify.passwords'))->deleteToken($user);

        event(new PasswordReset($user));

        Inertia::flash('memberPassword', [
            'memberId' => $user->id,
            'memberName' => $user->name,
            'password' => $password,
        ]);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('New password generated.')]);

        return to_route('teams.members.index', ['team' => $team->slug]);
    }

    /**
     * Send a password reset link to the specified team member.
     */
    public function sendPasswordResetLink(Team $team, User $user): RedirectResponse
    {
        Gate::authorize('updateMember', $team);
        $this->ensureMemberPasswordCanBeManaged($team, $user);

        $status = Password::broker(config('fortify.passwords'))->sendResetLink([
            Fortify::email() => $user->email,
        ]);

        Inertia::flash('toast', $status === Password::RESET_LINK_SENT
            ? ['type' => 'success', 'message' => __('Password reset link sent.')]
            : [
                'type' => 'error',
                'message' => $status === Password::RESET_THROTTLED
                    ? __('A password reset link was recently sent. Please wait before sending another.')
                    : __('Unable to send a password reset link. Please try again.'),
            ]);

        return to_route('teams.members.index', ['team' => $team->slug]);
    }

    /**
     * Remove the specified team member.
     */
    public function destroy(Team $team, User $user): RedirectResponse
    {
        Gate::authorize('removeMember', $team);

        abort_if($team->owner()?->is($user), 403, __('The team owner cannot be removed.'));

        $team->memberships()
            ->where('user_id', $user->id)
            ->firstOrFail()
            ->delete();

        if ($user->isCurrentTeam($team)) {
            $user->switchTeam($user->personalTeam());
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Member removed.')]);

        return to_route('teams.members.index', ['team' => $team->slug]);
    }

    /**
     * Ensure the user is a non-owner member of the selected team.
     */
    private function ensureMemberPasswordCanBeManaged(Team $team, User $user): void
    {
        $membership = $team->memberships()
            ->where('user_id', $user->id)
            ->firstOrFail();

        abort_if($membership->role === 'owner', 403, __('The team owner password cannot be managed.'));
    }

    /**
     * Generate a password that satisfies the production composition requirements.
     */
    private function newPassword(): string
    {
        do {
            $password = Str::password(20);
        } while (! preg_match('/[a-z]/', $password) || ! preg_match('/[A-Z]/', $password));

        return $password;
    }
}
