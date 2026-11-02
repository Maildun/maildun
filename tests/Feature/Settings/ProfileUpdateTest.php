<?php

use App\Models\User;
use App\Notifications\VerifyEmail;
use App\Services\DiceBearAvatarGenerator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
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
            'current_password' => 'password',
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
            'current_password' => 'password',
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
            'current_password' => 'password',
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
            'current_password' => 'password',
        ])
        ->assertRedirect(route('profile.edit'))
        ->assertSessionHasErrors(['email' => 'The email has already been taken.']);

    expect($user->refresh()->email)->not->toBe('taken@example.com');
});

test('changing the email address requires the current password', function (?string $password) {
    Notification::fake();

    $user = User::factory()->create();
    $originalEmail = $user->email;

    $this->actingAs($user)
        ->from(route('profile.edit'))
        ->patch(route('profile.update'), array_filter([
            'name' => $user->name,
            'email' => 'attacker@example.com',
            'current_password' => $password,
        ]))
        ->assertRedirect(route('profile.edit'))
        ->assertSessionHasErrors('current_password');

    expect($user->refresh()->email)->toBe($originalEmail)
        ->and($user->email_verified_at)->not->toBeNull();
    Notification::assertNothingSent();
})->with([
    'missing' => [null],
    'wrong' => ['wrong-password'],
]);

test('updating only the name needs no password', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Renamed',
            'email' => $user->email,
        ])
        ->assertSessionHasNoErrors();

    expect($user->refresh()->name)->toBe('Renamed');
});

test('an account without a password must confirm recently before changing its email', function () {
    Notification::fake();

    $user = User::factory()->withoutPassword()->withGoogle()->create();
    $originalEmail = $user->email;

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => 'attacker@example.com',
        ])
        ->assertRedirect(route('password.confirm'));

    expect($user->refresh()->email)->toBe($originalEmail);

    $this->withSession(['auth.password_confirmed_at' => time()])
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => 'new-address@example.com',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->email)->toBe('new-address@example.com')
        ->and($user->email_verified_at)->toBeNull();
    Notification::assertSentTo($user, VerifyEmail::class);
});

test('a new email address is stored lowercased', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => '  New-Address@Example.COM ',
            'current_password' => 'password',
        ])
        ->assertSessionHasNoErrors();

    expect($user->refresh()->email)->toBe('new-address@example.com');
});

test('profile update rejects a case variant of an email another account already uses', function () {
    $other = User::factory()->create();
    DB::table('users')->where('id', $other->id)->update(['email' => 'Ops@Example.com']);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('profile.edit'))
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => 'OPS@example.com',
            'current_password' => 'password',
        ])
        ->assertSessionHasErrors(['email' => 'The email has already been taken.']);

    expect($user->refresh()->email)->not->toBe('ops@example.com');
});

test('saving an email stored in mixed case keeps it verified and needs no password', function () {
    Notification::fake();

    $user = User::factory()->create();
    DB::table('users')->where('id', $user->id)->update(['email' => 'Mixed.Case@Example.com']);

    $this->actingAs($user->refresh())
        ->patch(route('profile.update'), [
            'name' => 'Renamed',
            'email' => 'Mixed.Case@Example.com',
        ])
        ->assertSessionHasNoErrors();

    expect($user->refresh()->email)->toBe('mixed.case@example.com')
        ->and($user->email_verified_at)->not->toBeNull();
    Notification::assertNothingSent();
});

test('an account sharing its address in another case with an older account can still update its name', function () {
    $first = User::factory()->create(['email' => 'ops@example.com']);
    DB::table('users')->where('id', $first->id)->update(['email' => 'Ops@Example.com']);
    $second = User::factory()->create(['email' => 'ops@example.com']);

    $this->actingAs($first->refresh())
        ->patch(route('profile.update'), [
            'name' => 'Renamed',
            'email' => 'Ops@Example.com',
        ])
        ->assertSessionHasNoErrors();

    expect($first->refresh())
        ->name->toBe('Renamed')
        ->email->toBe('Ops@Example.com')
        ->and($second->refresh()->email)->toBe('ops@example.com');
});
