<?php

use App\Models\User;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    config()->set('fortify.registration_open', true);
    Notification::fake();
});

test('registration returns 429 after five attempts from one IP without creating another account', function () {
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->postJson(route('register.store'), ['email' => "attempt{$attempt}@example.com"])
            ->assertUnprocessable();
    }

    $this->postJson(route('register.store'), [
        'name' => 'Blocked User', 'email' => 'blocked@example.com',
        'password' => 'password', 'password_confirmation' => 'password',
    ])->assertStatus(429)->assertHeader('Retry-After');

    $this->assertDatabaseCount('users', 0);
    Notification::assertNothingSent();
});

test('registration caps one IP at twenty attempts per hour across minute windows', function () {
    $this->freezeTime();

    for ($minute = 0; $minute < 4; $minute++) {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson(route('register.store'), ['email' => "attempt{$minute}-{$attempt}@example.com"])
                ->assertUnprocessable();
        }

        $this->travel(61)->seconds();
    }

    $this->postJson(route('register.store'), ['email' => 'hourly@example.com'])
        ->assertStatus(429)->assertHeader('Retry-After');
    Notification::assertNothingSent();
});

test('one normalized email is limited across different IP addresses', function () {
    foreach (['203.0.113.1', '203.0.113.2', '203.0.113.3'] as $ip) {
        $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->postJson(route('register.store'), ['email' => 'Target@Example.com'])
            ->assertUnprocessable();
    }

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.4'])
        ->postJson(route('register.store'), ['email' => '  target@example.com  '])
        ->assertStatus(429);

    $this->postJson(route('register.store'), ['email' => 'different@example.com'])
        ->assertUnprocessable();
    $this->assertDatabaseCount('users', 0);
    Notification::assertNothingSent();
});

test('registration remains available to another IP after one IP is throttled', function () {
    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.1']);
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->postJson(route('register.store'), ['email' => "attempt{$attempt}@example.com"])
            ->assertUnprocessable();
    }

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.2'])
        ->post(route('register.store'), [
            'name' => 'Allowed User', 'email' => 'allowed@example.com',
            'password' => 'password', 'password_confirmation' => 'password',
        ])->assertRedirect(route('dashboard'));

    $this->assertAuthenticated();
    $this->assertDatabaseHas('users', ['email' => 'allowed@example.com']);
    Notification::assertNothingSent();
});

test('registration throttling does not block auth pages or use the login allowance', function () {
    $user = User::factory()->create(['password' => 'password']);
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->postJson(route('register.store'), ['email' => "attempt{$attempt}@example.com"])
            ->assertUnprocessable();
    }

    $this->get(route('register'))->assertOk();
    $this->get(route('login'))->assertOk();
    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect();
    $this->assertAuthenticatedAs($user);
});

test('malformed email values cannot bypass the registration IP limit', function () {
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->postJson(route('register.store'), ['email' => "attempt{$attempt}@example.com"])
            ->assertUnprocessable();
    }

    $this->postJson(route('register.store'), ['email' => ['invalid']])->assertStatus(429);
    $this->assertDatabaseCount('users', 0);
});
