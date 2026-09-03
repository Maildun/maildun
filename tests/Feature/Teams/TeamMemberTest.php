<?php

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Notifications\ResetPassword;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

test('the team members page can be rendered', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);

    $this
        ->actingAs($user)
        ->get(route('teams.members.index', $team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('teams/members')
            ->where('members.data.0.role', TeamRole::Owner->value)
            ->where('members.data.0.role_label', TeamRole::Owner->label())
            ->where('members.data.0.uuid', $user->uuid),
        );
});

test('team members and pending invitations are paginated independently', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    User::factory()
        ->count(25)
        ->create()
        ->each(fn (User $member) => $team->members()->attach($member, ['role' => TeamRole::Member->value]));

    TeamInvitation::factory()
        ->count(11)
        ->create([
            'team_id' => $team->id,
            'invited_by' => $owner->id,
            'accepted_at' => null,
        ]);

    $this
        ->actingAs($owner)
        ->get(route('teams.members.index', [
            $team,
            'page' => 2,
            'members_per_page' => 25,
            'invitations_page' => 2,
            'invitations_per_page' => 10,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('members.data', 1)
            ->where('members.current_page', 2)
            ->where('members.per_page', 25)
            ->where('members.total', 26)
            ->has('invitations.data', 1)
            ->where('invitations.current_page', 2)
            ->where('invitations.per_page', 10)
            ->where('invitations.total', 11),
        );
});

test('users cannot view members of teams they do not belong to', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();

    $this
        ->actingAs($user)
        ->get(route('teams.members.index', $team))
        ->assertForbidden();
});

test('team member roles can be updated by owners', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $response = $this
        ->actingAs($owner)
        ->patch(route('teams.members.update', [$team, $member]), [
            'role' => TeamRole::Admin->value,
        ]);

    $response->assertRedirect(route('teams.members.index', $team));

    expect($team->members()->where('user_id', $member->id)->first()->pivot->role)->toEqual(TeamRole::Admin->value);
});

test('team member roles can be updated by admins', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $response = $this
        ->actingAs($admin)
        ->patch(route('teams.members.update', [$team, $member]), [
            'role' => TeamRole::Admin->value,
        ]);

    $response->assertRedirect(route('teams.members.index', $team));

    expect($team->members()->where('user_id', $member->id)->first()->pivot->role)
        ->toEqual(TeamRole::Admin->value);
});

test('team members can be removed by owners', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $response = $this
        ->actingAs($owner)
        ->delete(route('teams.members.destroy', [$team, $member]));

    $response->assertRedirect(route('teams.members.index', $team));

    expect($member->fresh()->belongsToTeam($team))->toBeFalse();
});

test('team members can be removed by admins', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $response = $this
        ->actingAs($admin)
        ->delete(route('teams.members.destroy', [$team, $member]));

    $response->assertRedirect(route('teams.members.index', $team));

    expect($member->fresh()->belongsToTeam($team))->toBeFalse();
});

test('team owner cannot be removed', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $response = $this
        ->actingAs($owner)
        ->delete(route('teams.members.destroy', [$team, $owner]));

    $response->assertForbidden();

    expect($owner->fresh()->belongsToTeam($team))->toBeTrue();
});

test('team owner role cannot be changed by admins', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);

    $this
        ->actingAs($admin)
        ->patch(route('teams.members.update', [$team, $owner]), [
            'role' => TeamRole::Member->value,
        ])
        ->assertForbidden();

    expect($owner->fresh()->teamRole($team))->toBe(TeamRole::Owner->value);
});

test('team member role cannot be set to owner', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $response = $this
        ->actingAs($owner)
        ->patch(route('teams.members.update', [$team, $member]), [
            'role' => TeamRole::Owner->value,
        ]);

    $response->assertSessionHasErrors('role');

    expect($team->members()->where('user_id', $member->id)->first()->pivot->role)->toEqual(TeamRole::Member->value);
});

test('removed member current team is set to personal team', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $personalTeam = $member->personalTeam();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $member->update(['current_team_id' => $team->id]);

    $this
        ->actingAs($owner)
        ->delete(route('teams.members.destroy', [$team, $member]));

    expect($member->fresh()->current_team_id)->toEqual($personalTeam->id);
});

test('team owners can generate a new password for a member', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $previousPassword = $member->password;
    $previousRememberToken = $member->remember_token;
    $passwordBroker = Password::broker(config('fortify.passwords'));
    $resetToken = $passwordBroker->createToken($member);

    Event::fake([PasswordReset::class]);

    $response = $this
        ->actingAs($owner)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('teams.members.generate-password', [$team, $member]));

    $response
        ->assertRedirect(route('teams.members.index', $team))
        ->assertInertiaFlash('memberPassword.memberId', $member->id)
        ->assertInertiaFlash('memberPassword.memberName', $member->name)
        ->assertInertiaFlash('memberPassword.password')
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'New password generated.']);

    Event::assertDispatched(PasswordReset::class, fn (PasswordReset $event): bool => $event->user->is($member));

    $firstVisit = $this
        ->actingAs($owner)
        ->get(route('teams.members.index', $team))
        ->assertInertia(fn (Assert $page) => $page
            ->hasFlash('memberPassword.memberId', $member->id)
            ->hasFlash('memberPassword.memberName', $member->name)
            ->hasFlash('memberPassword.password'),
        );

    $password = data_get($firstVisit->viewData('page'), 'flash.memberPassword.password');
    $freshMember = $member->fresh();

    expect($password)
        ->toBeString()
        ->and(Hash::check($password, $freshMember->password))->toBeTrue()
        ->and($freshMember->password)->not->toBe($previousPassword)
        ->and($freshMember->remember_token)->not->toBe($previousRememberToken)
        ->and($passwordBroker->tokenExists($freshMember, $resetToken))->toBeFalse();

    $this
        ->get(route('teams.members.index', $team))
        ->assertInertia(fn (Assert $page) => $page->missingFlash('memberPassword'));
});

test('team owners can send a password reset link to a member', function () {
    Queue::fake();

    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $this
        ->actingAs($owner)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('teams.members.send-password-reset-link', [$team, $member]))
        ->assertRedirect(route('teams.members.index', $team))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Password reset link sent.'])
        ->assertInertiaFlashMissing('memberPassword');

    Queue::assertPushed(SendQueuedNotifications::class, function (SendQueuedNotifications $job) use ($member): bool {
        return $job->notification instanceof ResetPassword
            && $job->notifiables->contains(fn (User $notifiable): bool => $notifiable->is($member));
    });
});

test('member password actions require a recent password confirmation', function (string $routeName) {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $this
        ->actingAs($owner)
        ->post(route($routeName, [$team, $member]))
        ->assertRedirect(route('password.confirm'));
})->with([
    'generate password' => 'teams.members.generate-password',
    'send reset link' => 'teams.members.send-password-reset-link',
]);

test('admins can manage member passwords', function (string $routeName) {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $this
        ->actingAs($admin)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route($routeName, [$team, $member]))
        ->assertRedirect(route('teams.members.index', $team));
})->with([
    'generate password' => 'teams.members.generate-password',
    'send reset link' => 'teams.members.send-password-reset-link',
]);

test('member password actions require the target to belong to the selected team', function (string $routeName) {
    $owner = User::factory()->create();
    $nonMember = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $this
        ->actingAs($owner)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route($routeName, [$team, $nonMember]))
        ->assertNotFound();
})->with([
    'generate password' => 'teams.members.generate-password',
    'send reset link' => 'teams.members.send-password-reset-link',
]);

test('team owners cannot manage their own passwords from member actions', function (string $routeName) {
    $owner = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $this
        ->actingAs($owner)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route($routeName, [$team, $owner]))
        ->assertForbidden();
})->with([
    'generate password' => 'teams.members.generate-password',
    'send reset link' => 'teams.members.send-password-reset-link',
]);
