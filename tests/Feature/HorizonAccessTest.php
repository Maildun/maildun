<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    config()->set('horizon.allowed_emails', ['ops@example.com']);
});

test('the operator gates admit only a verified allowlisted address', function (Closure $makeUser, bool $allowed) {
    $user = $makeUser();

    expect(Gate::forUser($user)->allows('viewHorizon'))->toBe($allowed)
        ->and(Gate::forUser($user)->allows('viewSystemCheck'))->toBe($allowed);
})->with([
    'verified allowlisted address' => [fn (): User => User::factory()->create(['email' => 'ops@example.com']), true],
    'unverified allowlisted address' => [fn (): User => User::factory()->unverified()->create(['email' => 'ops@example.com']), false],
    'verified address off the list' => [fn (): User => User::factory()->create(['email' => 'someone@example.com']), false],
    'verified address stored in another case' => [function (): User {
        $user = User::factory()->create(['email' => 'ops@example.com']);
        DB::table('users')->where('id', $user->id)->update(['email' => 'Ops@Example.com']);

        return $user->fresh();
    }, true],
]);

test('horizon opens for a verified allowlisted account', function () {
    $operator = User::factory()->create(['email' => 'ops@example.com']);

    $this->actingAs($operator)
        ->get('/horizon')
        ->assertOk();
});

test('horizon stays closed after an account renames itself to an allowlisted address', function () {
    Notification::fake();
    $user = User::factory()->create(['email' => 'attacker@example.com']);

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => 'Ops@Example.com',
        ])
        ->assertSessionHasNoErrors();

    $this->actingAs($user->refresh())
        ->get('/horizon')
        ->assertForbidden();
});
