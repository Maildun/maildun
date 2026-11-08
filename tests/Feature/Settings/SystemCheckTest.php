<?php

use App\Models\User;
use App\Services\AppUpdateChecker;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * A verified account listed in HORIZON_ALLOWED_EMAILS.
 */
function instanceOperator(): User
{
    config()->set('horizon.allowed_emails', ['ops@example.com']);

    return User::factory()->create(['email' => 'ops@example.com']);
}

test('guests are redirected from system checks', function () {
    $this->get(route('system-check.show'))
        ->assertRedirect(route('login'));
});

test('operators can view and run system checks', function () {
    Storage::fake('local');
    Storage::fake('local_public');
    config()->set([
        'filesystems.default' => 'local',
        'filesystems.disks.public' => config('filesystems.disks.local_public'),
    ]);

    $owner = instanceOperator();

    $this->actingAs($owner)
        ->get(route('system-check.show'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/system-check')
            ->has('checks', 10)
            ->where('checks.7.key', 'storage-round-trip')
            ->where('checks.7.status', 'pending')
            ->where('checks.8.key', 'scheduler')
            ->where('checks.9.key', 'campaign-sends')
            ->where('appUpdate.status', 'unknown'));

    $this->actingAs($owner)
        ->post(route('system-check.test'))
        ->assertRedirect(route('system-check.show'));

    $this->actingAs($owner)
        ->get(route('system-check.show'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/system-check')
            ->where('checks.7.key', 'storage-round-trip')
            ->where('checks.7.status', 'ready'));

    $this->actingAs($owner)
        ->get(route('system-check.show'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/system-check')
            ->where('checks.7.key', 'storage-round-trip')
            ->where('checks.7.status', 'ready'));

    Storage::disk('local')->assertDirectoryEmpty('/');
    Storage::disk('local_public')->assertDirectoryEmpty('/');
});

test('live checks stay current after a system test', function () {
    Storage::fake('local');
    Storage::fake('local_public');
    config()->set([
        'filesystems.default' => 'local',
        'filesystems.disks.public' => config('filesystems.disks.local_public'),
        'queue.default' => 'redis',
    ]);

    $owner = instanceOperator();

    $this->actingAs($owner)->post(route('system-check.test'))->assertRedirect();

    config()->set('queue.default', 'sync');

    $this->actingAs($owner)
        ->get(route('system-check.show'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('checks.4.key', 'queue')
            ->where('checks.4.status', 'failed')
            ->where('checks.7.key', 'storage-round-trip')
            ->where('checks.7.status', 'ready'));
});

test('operators can refresh the cached Maildun update status', function () {
    config()->set([
        'version.current' => '1.2.0',
        'version.update_check.enabled' => true,
        'version.update_check.manifest_url' => 'https://updates.example.test/release-manifest.json',
    ]);
    Http::preventStrayRequests();
    Http::fake([
        'https://updates.example.test/release-manifest.json' => Http::response([
            'latest_version' => '1.3.0',
            'release_url' => 'https://example.test/releases/1.3.0',
            'notes_url' => 'https://example.test/releases/1.3.0',
        ]),
    ]);
    $owner = instanceOperator();

    $this->actingAs($owner)
        ->post(route('app-update.refresh'))
        ->assertRedirect(route('system-check.show'));

    expect(app(AppUpdateChecker::class)->status())->toMatchArray([
        'status' => 'update_available',
        'latest_version' => '1.3.0',
    ]);
});

test('system checks forbid a workspace owner who is not an operator', function () {
    config()->set([
        'horizon.allowed_emails' => ['ops@example.com'],
        'version.update_check.enabled' => true,
        'version.update_check.manifest_url' => 'https://updates.example.test/release-manifest.json',
    ]);
    Http::fake();
    $owner = User::factory()->create();

    $this->actingAs($owner)
        ->get(route('system-check.show'))
        ->assertForbidden();

    $this->actingAs($owner)
        ->post(route('system-check.test'))
        ->assertForbidden();

    $this->actingAs($owner)
        ->post(route('app-update.refresh'))
        ->assertForbidden();

    expect($owner->ownsTeam($owner->currentTeam))->toBeTrue();
    Http::assertNothingSent();
});

test('the update status is shared only with operators', function (bool $isOperator) {
    $user = $isOperator ? instanceOperator() : User::factory()->create();

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertInertia(fn (Assert $page) => $isOperator
            ? $page->where('appUpdate.status', 'unknown')
            : $page->where('appUpdate', null));
})->with([
    'operator' => true,
    'workspace owner' => false,
]);
