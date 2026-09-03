<?php

use App\Services\DiceBearAvatarGenerator;
use Illuminate\Support\Facades\URL;

test('local avatar endpoints return deterministic SVGs', function (string $style) {
    $url = URL::signedRoute('avatars.show', [
        'style' => $style,
        'seed' => 'avatar-test',
    ], absolute: false);

    $firstResponse = $this->get($url)
        ->assertOk()
        ->assertHeader('content-type', 'image/svg+xml')
        ->assertHeader('cache-control', 'max-age=86400, public')
        ->assertHeaderMissing('set-cookie');

    $secondResponse = $this->get($url)->assertOk();

    expect($firstResponse->getContent())
        ->toStartWith('<svg')
        ->not->toContain('api.dicebear.com')
        ->toBe($secondResponse->getContent());
})->with([
    'critters' => DiceBearAvatarGenerator::CRITTERS,
    'loops' => DiceBearAvatarGenerator::LOOPS,
    'micah' => DiceBearAvatarGenerator::MICAH,
    'shape grid' => DiceBearAvatarGenerator::SHAPE_GRID,
]);

test('local avatar endpoints only accept supported styles', function () {
    $this->get('/avatars/unknown/avatar-test.svg')->assertNotFound();
});

test('local avatar endpoints require a valid signature', function () {
    $this->get(route('avatars.show', [
        'style' => DiceBearAvatarGenerator::MICAH,
        'seed' => 'avatar-test',
    ], absolute: false))->assertForbidden();
});

test('local avatar endpoint signatures cannot be reused for another seed', function () {
    $url = URL::signedRoute('avatars.show', [
        'style' => DiceBearAvatarGenerator::MICAH,
        'seed' => 'avatar-test',
    ], absolute: false);

    $this->get(str_replace('avatar-test', 'different-avatar', $url))->assertForbidden();
});
