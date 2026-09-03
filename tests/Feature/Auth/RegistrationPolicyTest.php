<?php

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function closeRegistration(): void
{
    config()->set('fortify.registration_open', false);
}

function openRegistration(): void
{
    config()->set('fortify.registration_open', true);
}

function pendingInvitation(string $email = 'invited@example.com'): TeamInvitation
{
    $owner = User::factory()->create();
    $team = Team::factory()->create(['name' => 'Laravel Team']);
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    return TeamInvitation::factory()->create([
        'team_id' => $team->id,
        'email' => $email,
        'invited_by' => $owner->id,
    ]);
}

test('the register routes stay registered so the frontend build keeps its helpers', function () {
    closeRegistration();

    expect(app('router')->has('register'))->toBeTrue()
        ->and(app('router')->has('register.store'))->toBeTrue();
});

test('a closed instance sends visitors to the login screen', function () {
    closeRegistration();

    $this->get(route('register'))
        ->assertRedirect(route('login'))
        ->assertSessionHas('status', 'Registration is closed. Ask an administrator to invite you.');
});

test('a closed instance refuses to create an account', function () {
    closeRegistration();

    $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect(route('login'));

    $this->assertGuest();
    expect(User::query()->where('email', 'test@example.com')->exists())->toBeFalse();
});

test('a closed instance answers json requests with a status code', function () {
    closeRegistration();

    $this->postJson(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertForbidden();
});

test('an invited person can still open the register screen while it is closed', function () {
    closeRegistration();
    $invitation = pendingInvitation();

    $this->get(route('register', ['invitation' => $invitation->code]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('auth/register'));
});

test('an invited person can still create their account while it is closed', function () {
    closeRegistration();
    pendingInvitation('invited@example.com');

    $this->post(route('register.store'), [
        'name' => 'Invited User',
        'email' => 'invited@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    expect(User::query()->where('email', 'invited@example.com')->exists())->toBeTrue();
});

test('an expired invitation does not reopen registration', function () {
    closeRegistration();
    $invitation = pendingInvitation();
    $invitation->update(['expires_at' => now()->subDay()]);

    $this->get(route('register', ['invitation' => $invitation->code]))
        ->assertRedirect(route('login'));

    $this->post(route('register.store'), [
        'name' => 'Invited User',
        'email' => $invitation->email,
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect(route('login'));

    $this->assertGuest();
});

test('an accepted invitation does not reopen registration', function () {
    closeRegistration();
    $invitation = pendingInvitation();
    $invitation->update(['accepted_at' => now()]);

    $this->get(route('register', ['invitation' => $invitation->code]))
        ->assertRedirect(route('login'));
});

test('the sign-up policy reaches the frontend', function () {
    openRegistration();

    $this->get(route('login'))->assertInertia(fn (Assert $page) => $page->where('registrationOpen', true));

    closeRegistration();

    $this->get(route('login'))->assertInertia(fn (Assert $page) => $page->where('registrationOpen', false));
});

test('an open instance is unaffected', function () {
    openRegistration();

    $this->get(route('register'))->assertOk();

    $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect(route('dashboard'));

    $this->assertAuthenticated();
});
