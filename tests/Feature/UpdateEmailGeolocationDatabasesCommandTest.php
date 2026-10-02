<?php

use App\Services\DbIpDatabaseInspector;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\PendingCommand;

use function Pest\Laravel\artisan;
use function Pest\Laravel\mock;
use function Pest\Laravel\travelTo;

/** @return array{directory: string, city: string, asn: string} */
function dbIpTestPaths(): array
{
    $directory = storage_path('framework/testing/dbip-'.Str::uuid());
    File::ensureDirectoryExists($directory);

    return [
        'directory' => $directory,
        'city' => $directory.'/dbip-city-lite.mmdb',
        'asn' => $directory.'/dbip-asn-lite.mmdb',
    ];
}

/** @param array{directory: string, city: string, asn: string} $paths */
function configureDbIpTestPaths(array $paths): void
{
    config()->set([
        'tracking.geolocation.enabled' => true,
        'tracking.geolocation.city_database' => $paths['city'],
        'tracking.geolocation.asn_database' => $paths['asn'],
        'tracking.geolocation.auto_update.enabled' => true,
        'tracking.geolocation.auto_update.download_base_url' => 'https://downloads.example.test/free',
    ]);
}

function gzipDbIpFixture(string $contents): string
{
    $archive = gzencode($contents);

    if ($archive === false) {
        throw new RuntimeException('Unable to create a compressed DB-IP test fixture.');
    }

    return $archive;
}

function updateDbIpArtisanCommand(): PendingCommand
{
    $command = artisan('emails:update-geolocation-databases');

    if (! $command instanceof PendingCommand) {
        throw new RuntimeException('The Laravel application is not available to the DB-IP command test.');
    }

    return $command;
}

test('it downloads validates and activates the current DB-IP Lite databases', function () {
    $paths = dbIpTestPaths();
    configureDbIpTestPaths($paths);
    travelTo('2026-09-14 10:00:00');
    Http::preventStrayRequests();
    Http::fake([
        'https://downloads.example.test/free/dbip-city-lite-2026-09.mmdb.gz' => Http::response(gzipDbIpFixture('city database')),
        'https://downloads.example.test/free/dbip-asn-lite-2026-09.mmdb.gz' => Http::response(gzipDbIpFixture('asn database')),
    ]);
    $inspector = mock(DbIpDatabaseInspector::class);
    $inspector->shouldReceive('releaseMonth')->twice()->andReturn(null);
    $inspector->shouldReceive('isValid')
        ->twice()
        ->andReturnUsing(fn (string $path, string $database): bool => File::get($path) === "{$database} database");

    try {
        updateDbIpArtisanCommand()
            ->expectsOutputToContain('City database')
            ->expectsOutputToContain('ASN database')
            ->assertSuccessful();

        expect(File::get($paths['city']))->toBe('city database');
        expect(File::get($paths['asn']))->toBe('asn database');
        Http::assertSentCount(2);
    } finally {
        File::deleteDirectory($paths['directory']);
    }
});

test('it writes downloads to relative database paths from the project root', function () {
    $relativeDirectory = 'storage/framework/testing/dbip-'.Str::uuid();
    $paths = [
        'directory' => $relativeDirectory,
        'city' => $relativeDirectory.'/dbip-city-lite.mmdb',
        'asn' => $relativeDirectory.'/dbip-asn-lite.mmdb',
    ];
    configureDbIpTestPaths($paths);
    travelTo('2026-09-14 10:00:00');
    Http::preventStrayRequests();
    Http::fake([
        'https://downloads.example.test/free/dbip-city-lite-2026-09.mmdb.gz' => Http::response(gzipDbIpFixture('city database')),
        'https://downloads.example.test/free/dbip-asn-lite-2026-09.mmdb.gz' => Http::response(gzipDbIpFixture('asn database')),
    ]);
    $inspector = mock(DbIpDatabaseInspector::class);
    $inspector->shouldReceive('releaseMonth')->twice()->andReturn(null);
    $inspector->shouldReceive('isValid')
        ->twice()
        ->andReturnUsing(fn (string $path, string $database): bool => File::get($path) === "{$database} database");

    try {
        updateDbIpArtisanCommand()->assertSuccessful();

        expect(File::get(base_path($paths['city'])))->toBe('city database');
        expect(File::get(base_path($paths['asn'])))->toBe('asn database');
    } finally {
        File::deleteDirectory(base_path($paths['directory']));
    }
});

test('it skips downloads when both installed databases are current', function () {
    $paths = dbIpTestPaths();
    configureDbIpTestPaths($paths);
    travelTo('2026-09-14 10:00:00');
    Http::preventStrayRequests();
    $inspector = mock(DbIpDatabaseInspector::class);
    $inspector->shouldReceive('releaseMonth')->twice()->andReturn('2026-09');

    try {
        updateDbIpArtisanCommand()
            ->expectsOutputToContain('Already current')
            ->assertSuccessful();

        Http::assertNothingSent();
    } finally {
        File::deleteDirectory($paths['directory']);
    }
});

test('it preserves an installed database when a downloaded replacement is invalid', function () {
    $paths = dbIpTestPaths();
    configureDbIpTestPaths($paths);
    File::put($paths['city'], 'working city database');
    travelTo('2026-09-14 10:00:00');
    Http::preventStrayRequests();
    Http::fake([
        'https://downloads.example.test/free/dbip-city-lite-2026-09.mmdb.gz' => Http::response(gzipDbIpFixture('invalid database')),
    ]);
    $inspector = mock(DbIpDatabaseInspector::class);
    $inspector->shouldReceive('releaseMonth')->once()->with($paths['city'])->andReturn('2026-08');
    $inspector->shouldReceive('isValid')->once()->withArgs(
        fn (string $path, string $database): bool => $database === 'city'
            && File::get($path) === 'invalid database',
    )->andReturnFalse();

    try {
        updateDbIpArtisanCommand()
            ->expectsOutputToContain('The failed replacement was not activated')
            ->assertFailed();

        expect(File::get($paths['city']))->toBe('working city database');
        Http::assertSentCount(1);
    } finally {
        File::deleteDirectory($paths['directory']);
    }
});

test('it makes no requests when automatic updates are disabled', function () {
    config()->set('tracking.geolocation.enabled', true);
    config()->set('tracking.geolocation.auto_update.enabled', false);
    Http::preventStrayRequests();

    updateDbIpArtisanCommand()
        ->expectsOutputToContain('Automatic DB-IP Lite updates are disabled.')
        ->assertSuccessful();

    Http::assertNothingSent();
});

test('it schedules a non-overlapping daily DB-IP Lite update', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn (mixed $event): bool => is_string($event->command)
            && str_contains($event->command, 'emails:update-geolocation-databases'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('30 2 * * *')
        ->and($event->withoutOverlapping)->toBeTrue()
        ->and($event->onOneServer)->toBeTrue();
});
