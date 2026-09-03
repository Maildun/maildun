<?php

use App\Services\AppUpdateChecker;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Cache::forget('app-update:status');
    config()->set([
        'version.current' => '1.2.0',
        'version.update_check.enabled' => true,
        'version.update_check.manifest_url' => 'https://updates.example.test/release-manifest.json',
    ]);
});

test('reports an available update from the official manifest', function () {
    Http::preventStrayRequests();
    Http::fake([
        'https://updates.example.test/release-manifest.json' => Http::response([
            'latest_version' => '1.3.0',
            'minimum_version' => '1.1.0',
            'release_url' => 'https://example.test/releases/1.3.0',
            'notes_url' => 'https://example.test/releases/1.3.0',
        ]),
    ]);

    $updates = app(AppUpdateChecker::class);

    expect($updates->refresh())->toBeTrue()
        ->and($updates->status())->toMatchArray([
            'status' => 'update_available',
            'current_version' => '1.2.0',
            'latest_version' => '1.3.0',
            'minimum_version' => '1.1.0',
        ]);

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://updates.example.test/release-manifest.json');
});

test('keeps the last successful result when the manifest cannot be reached', function () {
    Cache::forever('app-update:status', [
        'latest_version' => '1.3.0',
        'minimum_version' => '1.1.0',
        'release_url' => 'https://example.test/releases/1.3.0',
        'notes_url' => 'https://example.test/releases/1.3.0',
        'checked_at' => '2026-09-01T00:00:00+00:00',
    ]);
    Http::preventStrayRequests();
    Http::fake([
        'https://updates.example.test/release-manifest.json' => Http::failedConnection(),
    ]);

    $updates = app(AppUpdateChecker::class);

    expect($updates->refresh())->toBeFalse()
        ->and($updates->status())->toMatchArray([
            'status' => 'update_available',
            'latest_version' => '1.3.0',
        ]);
});

test('does not publish a malformed manifest as an update', function () {
    Http::preventStrayRequests();
    Http::fake([
        'https://updates.example.test/release-manifest.json' => Http::response([
            'latest_version' => 'invalid',
        ]),
    ]);

    $updates = app(AppUpdateChecker::class);

    expect($updates->refresh())->toBeFalse()
        ->and($updates->status()['status'])->toBe('unknown');
});

test('rejects a manifest whose minimum version is newer than its latest version', function () {
    Http::preventStrayRequests();
    Http::fake([
        'https://updates.example.test/release-manifest.json' => Http::response([
            'latest_version' => '1.3.0',
            'minimum_version' => '1.4.0',
            'release_url' => 'https://example.test/releases/1.3.0',
            'notes_url' => 'https://example.test/releases/1.3.0',
        ]),
    ]);

    expect(app(AppUpdateChecker::class)->refresh())->toBeFalse()
        ->and(app(AppUpdateChecker::class)->status()['status'])->toBe('unknown');
});

test('the shipped release manifest satisfies the update contract', function () {
    $manifest = json_decode(
        (string) file_get_contents(base_path('release-manifest.json')),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    config()->set('version.current', $manifest['latest_version']);

    Http::preventStrayRequests();
    Http::fake([
        'https://updates.example.test/release-manifest.json' => Http::response($manifest),
    ]);

    $updates = app(AppUpdateChecker::class);

    expect($updates->refresh())->toBeTrue()
        ->and($updates->status()['status'])->toBe('current');
});

test('the shipped release manifest links to the tag for its latest version', function () {
    $manifest = json_decode(
        (string) file_get_contents(base_path('release-manifest.json')),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    expect($manifest['release_url'])->toEndWith('/tag/v'.$manifest['latest_version'])
        ->and($manifest['notes_url'])->toEndWith('/tag/v'.$manifest['latest_version']);
});
