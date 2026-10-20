<?php

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

test('unverified users are sent to the verification notice from workspace pages', function () {
    $user = User::factory()->unverified()->create();
    $team = $user->personalTeam();

    $response = $this->actingAs($user)->get(route('dashboard', $team));

    $response->assertRedirect(route('verification.notice'));
});

test('the bare dashboard path leads a verified user to their workspace dashboard', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/dashboard')->assertRedirect('/');
    $this->actingAs($user)->get('/')->assertRedirect(route('dashboard', $user->currentTeam));
});

test('an unverified account cannot approve an MCP client', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->get(route('passport.authorizations.authorize'))
        ->assertRedirect(route('verification.notice'));
    $this->actingAs($user)
        ->post(route('passport.authorizations.approve'))
        ->assertRedirect(route('verification.notice'));
});

test('a new password revokes the oauth tokens issued to the account', function () {
    $user = User::factory()->create();
    $clientId = (string) Str::uuid();
    DB::table('oauth_access_tokens')->insert([
        'id' => 'access-token', 'user_id' => $user->id, 'client_id' => $clientId,
        'revoked' => false, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('oauth_refresh_tokens')->insert(['id' => 'refresh-token', 'access_token_id' => 'access-token', 'revoked' => false]);
    DB::table('oauth_auth_codes')->insert([
        'id' => 'auth-code', 'user_id' => $user->id, 'client_id' => $clientId, 'revoked' => false,
    ]);

    $user->forceFill(['password' => 'a-brand-new-password'])->save();

    expect(DB::table('oauth_access_tokens')->value('revoked'))->toBeTruthy()
        ->and(DB::table('oauth_refresh_tokens')->value('revoked'))->toBeTruthy()
        ->and(DB::table('oauth_auth_codes')->value('revoked'))->toBeTruthy();
});

test('email verification screen can be rendered', function () {
    $user = User::factory()->unverified()->create();

    $response = $this->actingAs($user)->get(route('verification.notice'));

    $response->assertOk();
});

test('unverified users are sent to the verification notice from workspace routes', function (string $routeName, string $parameter) {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->get(route($routeName, [$parameter => $user->currentTeam]))
        ->assertRedirect(route('verification.notice'));
})->with([
    'workspace dashboard' => ['dashboard', 'current_team'],
    'workspace contacts' => ['contacts.index', 'current_team'],
    'workspace settings' => ['teams.edit', 'team'],
]);

test('email can be verified', function () {
    $user = User::factory()->unverified()->create();
    $team = $user->personalTeam();

    Event::fake();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)],
    );

    $response = $this->actingAs($user)->get($verificationUrl);

    Event::assertDispatched(Verified::class);
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    $response->assertRedirect("/{$team->slug}/dashboard?verified=1");
});

test('email is not verified with invalid hash', function () {
    $user = User::factory()->unverified()->create();

    Event::fake();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1('wrong-email')],
    );

    $this->actingAs($user)->get($verificationUrl);

    Event::assertNotDispatched(Verified::class);
    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('email is not verified with invalid user id', function () {
    $user = User::factory()->unverified()->create();

    Event::fake();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => 123, 'hash' => sha1($user->email)],
    );

    $this->actingAs($user)->get($verificationUrl);

    Event::assertNotDispatched(Verified::class);
    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('verified user is redirected to dashboard from verification prompt', function () {
    $user = User::factory()->create();

    Event::fake();

    $response = $this->actingAs($user)->get(route('verification.notice'));

    Event::assertNotDispatched(Verified::class);
    $response->assertRedirect('/dashboard');
});

test('already verified user visiting verification link is redirected without firing event again', function () {
    $user = User::factory()->create();
    $team = $user->personalTeam();

    Event::fake();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)],
    );

    $this->actingAs($user)->get($verificationUrl)
        ->assertRedirect("/{$team->slug}/dashboard?verified=1");

    Event::assertNotDispatched(Verified::class);
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});
