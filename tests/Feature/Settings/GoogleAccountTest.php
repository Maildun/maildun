<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Socialite\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;

function configureGoogleCredentials(): void
{
    config()->set('services.google.client_id', 'google-client-id');
    config()->set('services.google.client_secret', 'google-client-secret');
    config()->set('services.google.redirect', 'http://localhost/auth/google/callback');
}

/**
 * Answer the Google callback with the account the person chose.
 */
function fakeChosenGoogleAccount(string $googleId = '109876543210'): void
{
    Socialite::fake('google', (new GoogleUser)->setRaw([
        'sub' => $googleId,
        'email' => 'ada@gmail.com',
        'email_verified' => true,
        'name' => 'Ada Lovelace',
    ])->map([
        'id' => $googleId,
        'name' => 'Ada Lovelace',
        'email' => 'ada@gmail.com',
    ]));
}

beforeEach(function () {
    configureGoogleCredentials();
});

test('the security page reports the google connection', function () {
    $user = User::factory()->withoutPassword()->withGoogle()->create();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('security.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/security')
            ->where('hasPassword', false)
            ->where('canManageGoogle', true)
            ->where('googleConnected', true),
        );
});

test('pages never share the google account id of the signed-in user', function () {
    $user = User::factory()->withGoogle('109876543210')->create();

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.id', $user->id)
            ->missing('auth.user.google_id'),
        );
});

test('the profile page reports whether the account has a password', function () {
    $user = User::factory()->withoutPassword()->withGoogle()->create();

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertInertia(fn (Assert $page) => $page->where('hasPassword', false));
});

test('google account settings respond 404 while google sign-in is not configured', function () {
    config()->set('services.google.client_secret', null);
    $user = User::factory()->withGoogle()->create();

    $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()]);

    $this->get(route('google.connect'))->assertNotFound();
    $this->delete(route('google.disconnect'))->assertNotFound();
    $this->get(route('google.confirm'))->assertNotFound();
});

test('connecting google requires a recent password confirmation', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('google.connect'))
        ->assertRedirect(route('password.confirm'));
});

test('connecting google links the chosen google account', function () {
    $user = User::factory()->create();
    fakeChosenGoogleAccount();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('google.connect'))
        ->assertRedirect('https://socialite.fake/google/authorize');
    $response = $this->get(route('google.callback'));

    $response->assertRedirect(route('security.edit'))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Google account connected.']);
    expect($user->fresh()->google_id)->toBe('109876543210');
});

test('connecting google refuses a google account connected to another user', function () {
    User::factory()->withGoogle('109876543210')->create();
    $user = User::factory()->create();
    fakeChosenGoogleAccount('109876543210');

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('google.connect'));
    $response = $this->get(route('google.callback'));

    $response->assertRedirect(route('security.edit'))
        ->assertInertiaFlash('toast', ['type' => 'error', 'message' => 'That Google account is already connected to another Maildun account.']);
    expect($user->fresh()->google_id)->toBeNull();
});

test('disconnecting google clears the connected google account', function () {
    $user = User::factory()->withGoogle()->create();

    $response = $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->delete(route('google.disconnect'));

    $response->assertRedirect(route('security.edit'))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Google account disconnected.']);
    expect($user->fresh()->google_id)->toBeNull();
});

test('disconnecting google is refused for an account without a password', function () {
    $user = User::factory()->withoutPassword()->withGoogle('109876543210')->create();

    $response = $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->delete(route('google.disconnect'));

    $response->assertRedirect(route('security.edit'))
        ->assertInertiaFlash('toast', ['type' => 'error', 'message' => 'Set a password before disconnecting Google, or you will not be able to log in.']);
    expect($user->fresh()->google_id)->toBe('109876543210');
});

test('the confirm password screen offers google to connected accounts', function () {
    $user = User::factory()->withoutPassword()->withGoogle()->create();

    $this->actingAs($user)
        ->get(route('password.confirm'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/confirm-password')
            ->where('hasPassword', false)
            ->where('canConfirmWithGoogle', true),
        );
});

test('choosing the connected google account confirms the password', function () {
    $user = User::factory()->withoutPassword()->withGoogle('109876543210')->create();
    fakeChosenGoogleAccount('109876543210');

    $this->actingAs($user)
        ->withSession(['url.intended' => route('security.edit')])
        ->get(route('google.confirm'))
        ->assertRedirect('https://socialite.fake/google/authorize');
    $response = $this->get(route('google.callback'));

    $response->assertRedirect(route('security.edit'))
        ->assertSessionHas('auth.password_confirmed_at');
});

test('choosing a different google account does not confirm the password', function () {
    $user = User::factory()->withoutPassword()->withGoogle('109876543210')->create();
    fakeChosenGoogleAccount('555555555555');

    $this->actingAs($user)->get(route('google.confirm'));
    $response = $this->get(route('google.callback'));

    $response->assertRedirect(route('password.confirm'))
        ->assertSessionHasErrors(['google' => 'Choose the Google account connected to your Maildun account.'])
        ->assertSessionMissing('auth.password_confirmed_at');
});

test('google confirmation is unavailable to accounts without a connected google account', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('google.confirm'))
        ->assertRedirect(route('password.confirm'))
        ->assertSessionHasErrors(['google' => 'Your account is not connected to a Google account.']);
});

test('an account without a password can set one after a recent confirmation', function () {
    $user = User::factory()->withoutPassword()->withGoogle()->create();

    $response = $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->from(route('security.edit'))
        ->put(route('user-password.update'), [
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('security.edit'))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Password set.']);
    expect(Hash::check('new-password', $user->fresh()->password))->toBeTrue();
});

test('an account without a password must confirm before setting one', function () {
    $user = User::factory()->withoutPassword()->withGoogle()->create();

    $response = $this->actingAs($user)
        ->from(route('security.edit'))
        ->put(route('user-password.update'), [
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

    $response->assertRedirect(route('password.confirm'))
        ->assertSessionHas('url.intended', route('security.edit'));
    expect($user->fresh()->password)->toBeNull();
});

test('an account with a password still needs its current password to change it', function () {
    $user = User::factory()->withGoogle()->create();

    $response = $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->from(route('security.edit'))
        ->put(route('user-password.update'), [
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

    $response->assertSessionHasErrors('current_password');
    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
});

test('an account without a password can be deleted after a recent confirmation', function () {
    $user = User::factory()->withoutPassword()->withGoogle()->create();

    $response = $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->delete(route('profile.destroy'));

    $response->assertSessionHasNoErrors()
        ->assertRedirect('/');
    $this->assertGuest();
    expect($user->fresh())->toBeNull();
});

test('an account without a password must confirm before it is deleted', function () {
    $user = User::factory()->withoutPassword()->withGoogle()->create();

    $response = $this->actingAs($user)
        ->from(route('profile.edit'))
        ->delete(route('profile.destroy'));

    $response->assertRedirect(route('password.confirm'))
        ->assertSessionHas('url.intended', route('profile.edit'));
    $this->assertAuthenticatedAs($user);
    expect($user->fresh())->not->toBeNull();
});

test('an account with a password still needs it to be deleted', function () {
    $user = User::factory()->withGoogle()->create();

    $response = $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->from(route('profile.edit'))
        ->delete(route('profile.destroy'));

    $response->assertSessionHasErrors('password')
        ->assertRedirect(route('profile.edit'));
    expect($user->fresh())->not->toBeNull();
});
