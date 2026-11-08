<?php

use App\Models\User;
use App\Notifications\VerifyEmail;
use App\Services\DiceBearAvatarGenerator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('profile.edit'));

    $response->assertOk();
});

test('profile information can be updated', function () {
    Notification::fake();

    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    expect($user->name)->toBe('Test User');
    expect($user->email)->toBe('test@example.com');
    expect($user->email_verified_at)->toBeNull();
    Notification::assertSentTo($user, VerifyEmail::class);
});

test('email verification status is unchanged when the email address is unchanged', function () {
    Notification::fake();

    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => $user->email,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->email_verified_at)->not->toBeNull();
    Notification::assertNothingSent();
});

test('changing the email address sends a verification link to the new address', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => 'new-address@example.com',
        ])
        ->assertSessionHasNoErrors();

    Notification::assertSentTo($user->refresh(), VerifyEmail::class);
});

test('keeping the email address sends no verification link', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => $user->email,
        ])
        ->assertSessionHasNoErrors();

    Notification::assertNothingSent();
});

test('profile avatar can be uploaded', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $avatar = UploadedFile::fake()->image('avatar.png');

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => $avatar,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    expect($user->avatar_path)->not->toBeNull()
        ->and($user->avatar)->toBe(Storage::disk('public')->url($user->avatar_path));
    Storage::disk('public')->assertExists($user->avatar_path);
});

test('a local critter avatar is generated when no profile avatar has been uploaded', function () {
    $user = User::factory()->create();

    $expectedAvatar = URL::signedRoute('avatars.show', [
        'style' => DiceBearAvatarGenerator::CRITTERS,
        'seed' => $user->uuid,
    ], absolute: false);

    expect($user->avatar)
        ->toBe($expectedAvatar)
        ->not->toContain('api.dicebear.com')
        ->and($user->fresh()->avatar)->toBe($expectedAvatar)
        ->and($user->toArray()['avatar'])->toBe($expectedAvatar);
});

test('user can delete their account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->delete(route('profile.destroy'), [
            'password' => 'password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('home'));

    $this->assertGuest();
    expect($user->fresh())->toBeNull();
});

test('correct password must be provided to delete account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('profile.edit'))
        ->delete(route('profile.destroy'), [
            'password' => 'wrong-password',
        ]);

    $response
        ->assertSessionHasErrors('password')
        ->assertRedirect(route('profile.edit'));

    expect($user->fresh())->not->toBeNull();
});

test('a changed email address must be verified again before verified pages open', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => 'new-address@example.com',
        ])
        ->assertSessionHasNoErrors();

    $this->get(route('dashboard'))->assertRedirect(route('verification.notice'));

    $this->post(route('verification.send'))->assertSessionHasNoErrors();

    Notification::assertSentTo(
        $user->refresh(),
        VerifyEmail::class,
        fn (VerifyEmail $notification, array $channels, User $notifiable): bool => $notifiable->email === 'new-address@example.com',
    );
});

test('profile update rejects an email address another account already uses', function () {
    $other = User::factory()->create(['email' => 'taken@example.com']);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('profile.edit'))
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $other->email,
        ])
        ->assertRedirect(route('profile.edit'))
        ->assertSessionHasErrors(['email' => 'The email has already been taken.']);

    expect($user->refresh()->email)->not->toBe('taken@example.com');
});

test('profile update rejects another spelling of an address another account already uses', function () {
    User::factory()->create(['email' => 'ops@example.com']);
    $user = User::factory()->create(['email' => 'attacker@example.com']);

    $this->actingAs($user)
        ->from(route('profile.edit'))
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => 'Ops@Example.com',
        ])
        ->assertRedirect(route('profile.edit'))
        ->assertSessionHasErrors(['email' => 'The email has already been taken.']);

    expect($user->refresh()->email)->toBe('attacker@example.com');
});

test('profile update stores the email address lowercased', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => 'New-Address@Example.COM',
        ])
        ->assertSessionHasNoErrors();

    expect($user->refresh()->email)->toBe('new-address@example.com');
});

test('changing only the case of the email address keeps it verified', function () {
    Notification::fake();

    $user = User::factory()->create(['email' => 'person@example.com']);

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => 'Person@Example.com',
        ])
        ->assertSessionHasNoErrors();

    expect($user->refresh()->email_verified_at)->not->toBeNull();
    Notification::assertNothingSent();
});
