<?php

use App\Models\User;
use Laravel\Fortify\Features;

test('an unverified user cannot request passkey registration options', function () {
    $this->skipUnlessFortifyHas(Features::passkeys());

    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->getJson(route('passkey.registration-options'))
        ->assertForbidden();
});

test('an unverified user cannot store a passkey', function () {
    $this->skipUnlessFortifyHas(Features::passkeys());

    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->postJson(route('passkey.store'), [
            'name' => 'Planted key',
            'credential' => [
                'id' => 'credential-id',
                'rawId' => 'credential-id',
                'type' => 'public-key',
                'response' => ['clientDataJSON' => 'data', 'attestationObject' => 'data'],
            ],
        ])
        ->assertForbidden();

    expect($user->passkeys()->exists())->toBeFalse();
});

test('an unverified user cannot enable two factor authentication', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('two-factor.enable'))
        ->assertRedirect(route('verification.notice'));

    expect($user->fresh()->two_factor_secret)->toBeNull();
});

test('an unverified user cannot confirm two factor authentication', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    $user = User::factory()->unverified()->create([
        'two_factor_secret' => encrypt('secret'),
    ]);

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('two-factor.confirm'), ['code' => '123456'])
        ->assertRedirect(route('verification.notice'));

    expect($user->fresh()->two_factor_confirmed_at)->toBeNull();
});

test('a verified user can request passkey registration options', function () {
    $this->skipUnlessFortifyHas(Features::passkeys());

    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->getJson(route('passkey.registration-options'))
        ->assertOk()
        ->assertJsonStructure(['options']);
});

test('a verified user reaches passkey validation when storing a passkey', function () {
    $this->skipUnlessFortifyHas(Features::passkeys());

    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->postJson(route('passkey.store'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'credential']);
});

test('a verified user can enable two factor authentication', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('two-factor.enable'))
        ->assertSessionHasNoErrors();

    expect($user->fresh()->two_factor_secret)->not->toBeNull();
});
