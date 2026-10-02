<?php

use App\Services\DbIpDatabasePath;
use Tests\TestCase;

uses(TestCase::class);

test('relative geoip paths are resolved from the project root', function () {
    expect(DbIpDatabasePath::resolve('storage/app/private/geoip/dbip-city-lite.mmdb'))
        ->toBe(base_path('storage/app/private/geoip/dbip-city-lite.mmdb'));
});

test('absolute geoip paths are left unchanged', function () {
    $path = storage_path('app/private/geoip/dbip-asn-lite.mmdb');

    expect(DbIpDatabasePath::resolve($path))->toBe($path);
});

test('blank geoip paths resolve to null', function () {
    expect(DbIpDatabasePath::resolve(null))->toBeNull()
        ->and(DbIpDatabasePath::resolve(''))->toBeNull()
        ->and(DbIpDatabasePath::resolve('   '))->toBeNull();
});
