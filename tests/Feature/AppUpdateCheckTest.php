<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Http;

test('checks for updates from the configured manifest command', function () {
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

    $this->artisan('app:check-for-updates')
        ->expectsOutputToContain('A Maildun update is available.')
        ->assertSuccessful();
});

test('schedules the update check once per day', function () {
    $commands = collect(app(Schedule::class)->events())
        ->pluck('command')
        ->filter(fn (mixed $command): bool => is_string($command));

    expect($commands->contains(
        fn (string $command): bool => str_contains($command, 'app:check-for-updates'),
    ))->toBeTrue();
});

test('does not request the release manifest when update checks are disabled', function () {
    config()->set('version.update_check.enabled', false);
    Http::preventStrayRequests();

    $this->artisan('app:check-for-updates')
        ->expectsOutputToContain('Maildun update checks are disabled.')
        ->assertSuccessful();

    Http::assertNothingSent();
});
