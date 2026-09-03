<?php

use App\Enums\TeamRole;
use App\Models\User;
use App\Models\WorkspaceRole;
use App\Services\AppUpdateChecker;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected from system checks', function () {
    $this->get(route('system-check.show'))
        ->assertRedirect(route('login'));
});

test('owners can view and run system checks', function () {
    Storage::fake('local');
    Storage::fake('local_public');
    config()->set([
        'filesystems.default' => 'local',
        'filesystems.disks.public' => config('filesystems.disks.local_public'),
    ]);

    $owner = User::factory()->create();

    $this->actingAs($owner)
        ->get(route('system-check.show'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/system-check')
            ->has('checks', 8)
            ->where('checks.7.key', 'storage-round-trip')
            ->where('checks.7.status', 'pending')
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

test('owners can refresh the cached Maildun update status', function () {
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
    $owner = User::factory()->create();

    $this->actingAs($owner)
        ->post(route('app-update.refresh'))
        ->assertRedirect(route('system-check.show'));

    expect(app(AppUpdateChecker::class)->status())->toMatchArray([
        'status' => 'update_available',
        'latest_version' => '1.3.0',
    ]);
});

test('system checks forbid non-owner workspace roles', function (string $role) {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    if ($role === 'support') {
        WorkspaceRole::create([
            'team_id' => $team->id,
            'name' => $role,
            'label' => 'Support',
            'guard_name' => 'web',
        ]);
    }

    $team->memberships()
        ->where('user_id', $user->id)
        ->sole()
        ->update(['role' => $role]);

    $this->actingAs($user)
        ->get(route('system-check.show'))
        ->assertForbidden();

    $this->actingAs($user)
        ->post(route('system-check.test'))
        ->assertForbidden();
})->with([
    'administrator' => TeamRole::Admin->value,
    'member' => TeamRole::Member->value,
    'custom workspace role' => 'support',
]);
