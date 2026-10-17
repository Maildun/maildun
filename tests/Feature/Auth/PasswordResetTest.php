<?php

use App\Models\User;
use App\Notifications\ResetPassword;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Queue;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::resetPasswords());
});

test('reset password link screen can be rendered', function () {
    $response = $this->get(route('password.request'));

    $response->assertOk();
});

test('reset password link can be requested', function () {
    Queue::fake();

    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email]);

    Queue::assertPushed(SendQueuedNotifications::class, function (SendQueuedNotifications $job) use ($user): bool {
        return $job->notification instanceof ResetPassword
            && $job->notifiables->contains(fn (User $notifiable): bool => $notifiable->is($user));
    });
});

test('reset password screen can be rendered', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
        $response = $this->get(route('password.reset', $notification->token));

        $response->assertOk();

        return true;
    });
});

test('password can be reset with valid token', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
        $response = $this->post(route('password.update'), [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('login'));

        return true;
    });
});

test('resetting an unverified account removes its passkeys and two factor authentication', function () {
    $user = User::factory()->unverified()->withTwoFactor()->create();
    $user->passkeys()->create([
        'name' => 'Planted key',
        'credential_id' => 'planted-credential',
        'credential' => ['publicKey' => 'key'],
    ]);

    $this->post(route('password.update'), [
        'token' => Password::broker(config('fortify.passwords'))->createToken($user),
        'email' => $user->email,
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ])->assertSessionHasNoErrors();

    $user->refresh();

    expect($user->passkeys()->exists())->toBeFalse()
        ->and($user->two_factor_secret)->toBeNull()
        ->and($user->two_factor_recovery_codes)->toBeNull()
        ->and($user->two_factor_confirmed_at)->toBeNull();
});

test('resetting a verified account keeps its passkeys and two factor authentication', function () {
    $user = User::factory()->withTwoFactor()->create();
    $passkey = $user->passkeys()->create([
        'name' => 'Owner key',
        'credential_id' => 'owner-credential',
        'credential' => ['publicKey' => 'key'],
    ]);

    $this->post(route('password.update'), [
        'token' => Password::broker(config('fortify.passwords'))->createToken($user),
        'email' => $user->email,
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ])->assertSessionHasNoErrors();

    $user->refresh();

    expect($user->passkeys()->whereKey($passkey->id)->exists())->toBeTrue()
        ->and($user->two_factor_secret)->not->toBeNull()
        ->and($user->two_factor_recovery_codes)->not->toBeNull()
        ->and($user->two_factor_confirmed_at)->not->toBeNull();
});

test('password cannot be reset with invalid token', function () {
    $user = User::factory()->create();

    $response = $this->post(route('password.update'), [
        'token' => 'invalid-token',
        'email' => $user->email,
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ]);

    $response->assertSessionHasErrors('email');
});
