<?php

use App\Models\TeamInvitation;
use App\Models\User;
use App\Services\GoogleSignIn;
use App\Services\InstallationState;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Events\TwoFactorAuthenticationChallenged;
use Laravel\Socialite\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as GoogleUser;

function enableGoogleSignIn(): void
{
    config()->set('services.google.client_id', 'google-client-id');
    config()->set('services.google.client_secret', 'google-client-secret');
    config()->set('services.google.redirect', 'http://localhost/auth/google/callback');
}

/**
 * Answer the Google callback with the given userinfo claims.
 *
 * @param  array<string, mixed>  $claims
 */
function fakeGoogleAccount(array $claims = []): void
{
    $claims = [
        'sub' => '109876543210',
        'email' => 'ada@example.com',
        'email_verified' => true,
        'name' => 'Ada Lovelace',
        ...$claims,
    ];

    Socialite::fake('google', (new GoogleUser)->setRaw($claims)->map([
        'id' => $claims['sub'],
        'name' => $claims['name'],
        'email' => $claims['email'],
    ]));
}

beforeEach(function () {
    enableGoogleSignIn();
});

test('google routes respond 404 until both google credentials are configured', function (?string $clientId, ?string $clientSecret) {
    config()->set('services.google.client_id', $clientId);
    config()->set('services.google.client_secret', $clientSecret);

    $this->get(route('google.redirect'))->assertNotFound();
    $this->get(route('google.callback'))->assertNotFound();
})->with([
    'no client id' => [null, 'google-client-secret'],
    'no client secret' => ['google-client-id', ''],
]);

test('the login and register screens offer google only when it is configured', function () {
    config()->set('fortify.registration_open', true);

    $this->get(route('login'))->assertInertia(fn (Assert $page) => $page->where('canLoginWithGoogle', true));
    $this->get(route('register'))->assertInertia(fn (Assert $page) => $page->where('canLoginWithGoogle', true));

    config()->set('services.google.client_id', null);

    $this->get(route('login'))->assertInertia(fn (Assert $page) => $page->where('canLoginWithGoogle', false));
    $this->get(route('register'))->assertInertia(fn (Assert $page) => $page->where('canLoginWithGoogle', false));
});

test('the redirect remembers the login and invitation before sending the browser to google', function () {
    Socialite::fake('google');

    $this->get(route('google.redirect', ['invitation' => 'invitation-code']))
        ->assertRedirect('https://socialite.fake/google/authorize')
        ->assertSessionHas(GoogleSignIn::SessionKey, ['intent' => 'login', 'invitation' => 'invitation-code']);
});

test('the redirect asks google to let the person choose an account', function () {
    $response = $this->get(route('google.redirect'));

    expect($response->headers->get('Location'))
        ->toStartWith('https://accounts.google.com/o/oauth2/auth?')
        ->toContain('client_id=google-client-id')
        ->toContain('redirect_uri='.urlencode('http://localhost/auth/google/callback'))
        ->toContain('prompt=select_account');
});

test('a linked google account logs in to its current workspace dashboard', function () {
    $user = User::factory()->withGoogle('109876543210')->create(['email' => 'ada@example.com']);
    fakeGoogleAccount(['email' => 'ada.lovelace@gmail.com']);

    $this->get(route('google.redirect'));
    $response = $this->get(route('google.callback', ['code' => 'code', 'state' => 'state']));

    $response->assertRedirect(route('dashboard', ['current_team' => $user->currentTeam->slug]));
    $this->assertAuthenticatedAs($user);
});

test('google login returns to the page intended before login', function () {
    User::factory()->withGoogle('109876543210')->create();
    fakeGoogleAccount();

    $this->withSession(['url.intended' => 'http://localhost/oauth/authorize?client_id=client-id'])
        ->get(route('google.redirect'));
    $response = $this->get(route('google.callback'));

    $response->assertRedirect('http://localhost/oauth/authorize?client_id=client-id');
    $this->assertAuthenticated();
});

test('google links the existing account that has the same verified address', function () {
    $user = User::factory()->create(['email' => 'ada@example.com']);
    fakeGoogleAccount(['email' => ' ADA@Example.com ']);

    $this->get(route('google.redirect'));
    $response = $this->get(route('google.callback'));

    $response->assertRedirect(route('dashboard', ['current_team' => $user->currentTeam->slug]));
    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->google_id)->toBe('109876543210');
});

test('google does not link an account whose address was never verified', function () {
    $user = User::factory()->unverified()->create(['email' => 'ada@example.com']);
    fakeGoogleAccount();

    $this->get(route('google.redirect'));
    $response = $this->get(route('google.callback'));

    $response->assertRedirect(route('login'))
        ->assertSessionHasErrors(['google' => 'An unverified account already uses this email address. Log in with your password or reset it, then connect Google from Security settings.']);
    $this->assertGuest();
    expect($user->fresh()->google_id)->toBeNull();
});

test('google does not link or create an account for an address google has not verified', function (mixed $emailVerified) {
    $user = User::factory()->create(['email' => 'ada@example.com']);
    fakeGoogleAccount(['email_verified' => $emailVerified]);

    $this->get(route('google.redirect'));
    $response = $this->get(route('google.callback'));

    $response->assertRedirect(route('login'))
        ->assertSessionHasErrors(['google' => 'Google has not verified the email address on that account, so it cannot be used to sign in.']);
    $this->assertGuest();
    expect($user->fresh()->google_id)->toBeNull()
        ->and(User::query()->count())->toBe(1);
})->with([
    'unverified' => [false],
    'missing claim' => [null],
    'string claim' => ['true'],
]);

test('google does not link an account connected to a different google account', function () {
    $user = User::factory()->withGoogle('555555555555')->create(['email' => 'ada@example.com']);
    fakeGoogleAccount();

    $this->get(route('google.redirect'));
    $response = $this->get(route('google.callback'));

    $response->assertRedirect(route('login'))
        ->assertSessionHasErrors(['google' => 'The account using this email address is connected to a different Google account.']);
    $this->assertGuest();
    expect($user->fresh()->google_id)->toBe('555555555555');
});

test('google creates a verified password-less account while registration is open', function () {
    config()->set('fortify.registration_open', true);
    app(InstallationState::class)->markComplete();
    Event::fake([Registered::class]);
    fakeGoogleAccount(['email' => 'Ada@Example.com']);

    $this->get(route('google.redirect'));
    $response = $this->get(route('google.callback'));

    $user = User::query()->sole();
    $response->assertRedirect(route('dashboard', ['current_team' => $user->currentTeam->slug]));
    $this->assertAuthenticatedAs($user);
    expect($user)
        ->name->toBe('Ada Lovelace')
        ->email->toBe('ada@example.com')
        ->google_id->toBe('109876543210')
        ->password->toBeNull()
        ->email_verified_at->not->toBeNull()
        ->and($user->currentTeam)
        ->is_personal->toBeTrue()
        ->name->toBe("Ada Lovelace's Team");
    Event::assertDispatched(Registered::class, fn (Registered $event) => $event->user->is($user));
});

test('google names a new account after its address when google has no name', function () {
    config()->set('fortify.registration_open', true);
    app(InstallationState::class)->markComplete();
    fakeGoogleAccount(['name' => '  ']);

    $this->get(route('google.redirect'));
    $this->get(route('google.callback'));

    expect(User::query()->sole()->name)->toBe('ada');
});

test('google does not create an account before installation is complete', function () {
    config()->set('fortify.registration_open', true);
    fakeGoogleAccount();

    $this->get(route('google.redirect'));
    $response = $this->get(route('google.callback'));

    $response->assertRedirect(route('login'))
        ->assertSessionHasErrors(['google' => 'Maildun has not finished installing yet. Complete the installation before signing up with Google.']);
    $this->assertGuest();
    expect(User::query()->exists())->toBeFalse();
});

test('google does not create an account while registration is closed', function () {
    config()->set('fortify.registration_open', false);
    app(InstallationState::class)->markComplete();
    fakeGoogleAccount();

    $this->get(route('google.redirect'));
    $response = $this->get(route('google.callback'));

    $response->assertRedirect(route('login'))
        ->assertSessionHasErrors(['google' => 'Registration is closed. Ask an administrator to invite you.']);
    $this->assertGuest();
    expect(User::query()->exists())->toBeFalse();
});

test('google creates an invited account while registration is closed', function () {
    config()->set('fortify.registration_open', false);
    TeamInvitation::factory()->create(['email' => 'Ada@Example.com']);
    fakeGoogleAccount();

    $this->get(route('google.redirect'));
    $response = $this->get(route('google.callback'));

    $user = User::query()->where('google_id', '109876543210')->sole();
    $response->assertRedirect(route('dashboard', ['current_team' => $user->currentTeam->slug]));
    $this->assertAuthenticatedAs($user);
});

test('used up invitations do not reopen google sign-up', function (string $state) {
    config()->set('fortify.registration_open', false);
    TeamInvitation::factory()->{$state}()->create(['email' => 'ada@example.com']);
    fakeGoogleAccount();

    $this->get(route('google.redirect'));
    $response = $this->get(route('google.callback'));

    $response->assertRedirect(route('login'))
        ->assertSessionHasErrors(['google' => 'Registration is closed. Ask an administrator to invite you.']);
    $this->assertGuest();
    expect(User::query()->where('email', 'ada@example.com')->exists())->toBeFalse();
})->with(['expired', 'accepted']);

test('an invitation for another address keeps its code when google sign-up is refused', function () {
    config()->set('fortify.registration_open', false);
    $invitation = TeamInvitation::factory()->create(['email' => 'invited@example.com']);
    fakeGoogleAccount();

    $this->get(route('google.redirect', ['invitation' => $invitation->code]));
    $response = $this->get(route('google.callback'));

    $response->assertRedirect(route('login', ['invitation' => $invitation->code]))
        ->assertSessionHasErrors(['google' => 'This invitation was sent to a different email address. Continue with the Google account for that address.']);
    $this->assertGuest();
    expect(User::query()->where('email', 'ada@example.com')->exists())->toBeFalse();
});

test('google sign-up is limited per network address', function () {
    config()->set('fortify.registration_open', true);
    app(InstallationState::class)->markComplete();
    RateLimiter::increment('google-registration:127.0.0.1', 3600, 20);
    fakeGoogleAccount();

    $this->get(route('google.redirect'));
    $response = $this->get(route('google.callback'));

    $response->assertRedirect(route('login'))
        ->assertSessionHasErrors(['google' => 'Too many accounts have been created from your network. Please try again later.']);
    expect(User::query()->exists())->toBeFalse();
});

test('google challenges accounts with two factor authentication before logging them in', function () {
    $user = User::factory()->withTwoFactor()->withGoogle('109876543210')->create();
    Event::fake([TwoFactorAuthenticationChallenged::class]);
    fakeGoogleAccount();

    $this->get(route('google.redirect'));
    $response = $this->get(route('google.callback'));

    $response->assertRedirect(route('two-factor.login'))
        ->assertSessionHas('login.id', $user->id);
    $this->assertGuest();
    Event::assertDispatched(TwoFactorAuthenticationChallenged::class, fn (TwoFactorAuthenticationChallenged $event) => $event->user->is($user));
});

test('a cancelled google sign-in returns to the login screen', function () {
    User::factory()->withGoogle('109876543210')->create();
    fakeGoogleAccount();

    $this->get(route('google.redirect'));
    $response = $this->get(route('google.callback', ['error' => 'access_denied']));

    $response->assertRedirect(route('login'))
        ->assertSessionHasErrors(['google' => 'Google sign-in was cancelled.']);
    $this->assertGuest();
});

test('a google callback with a mismatched state returns to the login screen without reporting it', function () {
    Exceptions::fake();
    Socialite::fake('google', fn () => throw new InvalidStateException);

    $this->get(route('google.redirect'));
    $response = $this->get(route('google.callback'));

    $response->assertRedirect(route('login'))
        ->assertSessionHasErrors(['google' => 'We could not verify your Google account. Please try again.']);
    $this->assertGuest();
    Exceptions::assertNothingReported();
});

test('a failing google token exchange is reported and returns to the login screen', function () {
    Exceptions::fake();
    Socialite::fake('google', fn () => throw new RuntimeException('Google is unavailable.'));

    $this->get(route('google.redirect'));
    $response = $this->get(route('google.callback'));

    $response->assertRedirect(route('login'))
        ->assertSessionHasErrors(['google' => 'We could not verify your Google account. Please try again.']);
    Exceptions::assertReported(RuntimeException::class);
});

test('a google callback that no redirect started is refused', function () {
    User::factory()->withGoogle('109876543210')->create();
    fakeGoogleAccount();

    $response = $this->get(route('google.callback'));

    $response->assertRedirect(route('login'))
        ->assertSessionHasErrors(['google' => 'We could not verify your Google account. Please try again.']);
    $this->assertGuest();
});
