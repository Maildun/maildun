<?php

use App\Models\User;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Features;
use PragmaRX\Google2FA\Google2FA;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());
});

/**
 * Give the user a real, confirmed TOTP secret and the given recovery codes.
 *
 * @param  list<string>  $recoveryCodes
 */
function enableTwoFactorWithRealSecret(User $user, array $recoveryCodes = ['recovery-code-1', 'recovery-code-2']): string
{
    $secret = app(TwoFactorAuthenticationProvider::class)->generateSecretKey();

    $user->forceFill([
        'two_factor_secret' => encrypt($secret),
        'two_factor_recovery_codes' => encrypt(json_encode($recoveryCodes)),
        'two_factor_confirmed_at' => now(),
    ])->save();

    return $secret;
}

function currentTotp(string $secret): string
{
    return app(Google2FA::class)->getCurrentOtp($secret);
}

test('turning on two factor requires a recent password confirmation', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('two-factor.enable'))
        ->assertRedirect(route('password.confirm'));

    expect($user->fresh()->two_factor_secret)->toBeNull();
});

test('turning on two factor stores an unconfirmed secret with a qr code and recovery codes', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('two-factor.enable'))
        ->assertRedirect();

    $user->refresh();
    expect($user->two_factor_secret)->not->toBeNull()
        ->and($user->two_factor_confirmed_at)->toBeNull()
        ->and($user->recoveryCodes())->toHaveCount(8);

    $this->getJson(route('two-factor.qr-code'))
        ->assertOk()
        ->assertJsonStructure(['svg', 'url']);

    $this->getJson(route('two-factor.secret-key'))
        ->assertOk()
        ->assertJsonPath('secretKey', decrypt($user->two_factor_secret));
});

test('two factor is confirmed with a code from the authenticator app', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()]);
    $this->post(route('two-factor.enable'));
    $secret = decrypt($user->fresh()->two_factor_secret);

    $this->post(route('two-factor.confirm'), ['code' => currentTotp($secret)])
        ->assertSessionHasNoErrors();

    expect($user->fresh()->two_factor_confirmed_at)->not->toBeNull();
});

test('two factor is not confirmed with a wrong code', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()]);
    $this->post(route('two-factor.enable'));

    $this->post(route('two-factor.confirm'), ['code' => '000000'])
        ->assertSessionHasErrorsIn('confirmTwoFactorAuthentication', 'code');

    expect($user->fresh()->two_factor_confirmed_at)->toBeNull();
});

test('recovery codes can be shown and regenerated', function () {
    $user = User::factory()->create();
    enableTwoFactorWithRealSecret($user, ['old-code-1', 'old-code-2']);

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->getJson(route('two-factor.recovery-codes'))
        ->assertOk()
        ->assertExactJson(['old-code-1', 'old-code-2']);

    $this->post(route('two-factor.regenerate-recovery-codes'))->assertRedirect();

    expect($user->fresh()->recoveryCodes())
        ->toHaveCount(8)
        ->not->toContain('old-code-1');
});

test('the login challenge accepts a code from the authenticator app', function () {
    $user = User::factory()->create();
    $secret = enableTwoFactorWithRealSecret($user);

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('two-factor.login'));

    $this->post(route('two-factor.login.store'), ['code' => currentTotp($secret)])
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
});

test('the login challenge rejects a wrong code', function () {
    $user = User::factory()->create();
    enableTwoFactorWithRealSecret($user);

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);

    $this->post(route('two-factor.login.store'), ['code' => '000000'])
        ->assertRedirect(route('two-factor.login'))
        ->assertSessionHasErrors('code');

    $this->assertGuest();
});

test('the login challenge accepts a recovery code once', function () {
    $user = User::factory()->create();
    enableTwoFactorWithRealSecret($user, ['recovery-code-1', 'recovery-code-2']);

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);
    $this->post(route('two-factor.login.store'), ['recovery_code' => 'recovery-code-1'])
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->recoveryCodes())
        ->not->toContain('recovery-code-1')
        ->toContain('recovery-code-2')
        ->toHaveCount(2);

    $this->post(route('logout'));
    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);
    $this->post(route('two-factor.login.store'), ['recovery_code' => 'recovery-code-1'])
        ->assertSessionHasErrors('recovery_code');

    $this->assertGuest();
});

test('turning off two factor clears the secret and recovery codes', function () {
    $user = User::factory()->create();
    enableTwoFactorWithRealSecret($user);

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->delete(route('two-factor.disable'))
        ->assertRedirect();

    $user->refresh();
    expect($user->two_factor_secret)->toBeNull()
        ->and($user->two_factor_recovery_codes)->toBeNull()
        ->and($user->two_factor_confirmed_at)->toBeNull();

    $this->post(route('logout'));
    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('dashboard'));
});

test('turning off two factor requires a recent password confirmation', function () {
    $user = User::factory()->create();
    enableTwoFactorWithRealSecret($user);

    $this->actingAs($user)
        ->delete(route('two-factor.disable'))
        ->assertRedirect(route('password.confirm'));

    expect($user->fresh()->two_factor_confirmed_at)->not->toBeNull();
});
