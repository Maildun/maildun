<?php

use App\Models\User;
use Illuminate\Http\Request;
use Laravel\Horizon\Horizon;

beforeEach(function () {
    config(['horizon.allowed_emails' => ['ops@example.com']]);
});

function horizonAdmits(?User $user): bool
{
    $request = Request::create('/'.config('horizon.path'));
    $request->setUserResolver(fn (): ?User => $user);

    return Horizon::check($request);
}

test('a verified account on the allowlist can open horizon', function () {
    $user = User::factory()->create(['email' => 'ops@example.com']);

    $this->actingAs($user);

    expect(horizonAdmits($user))->toBeTrue();
});

test('an unverified account cannot open horizon even with an allowlisted address', function () {
    $user = User::factory()->unverified()->create(['email' => 'ops@example.com']);

    $this->actingAs($user);

    expect(horizonAdmits($user))->toBeFalse();
});

test('a verified account off the allowlist cannot open horizon', function () {
    $user = User::factory()->create(['email' => 'someone@example.com']);

    $this->actingAs($user);

    expect(horizonAdmits($user))->toBeFalse();
});

test('horizon stays closed to guests and outsiders in the local environment', function () {
    $this->app['env'] = 'local';

    expect(horizonAdmits(null))->toBeFalse();

    $this->get('/'.config('horizon.path'))->assertForbidden();

    $outsider = User::factory()->create(['email' => 'someone@example.com']);
    $this->actingAs($outsider);

    expect(horizonAdmits($outsider))->toBeFalse();
});
