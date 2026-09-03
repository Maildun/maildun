<?php

use App\Actions\Media\ConvertImageToWebp;

test('it encodes a png as webp', function () {
    $png = fakePng(100, 80);

    $converted = (new ConvertImageToWebp)->handle($png);

    $image = imagecreatefromstring($converted['contents']);

    expect($converted['width'])->toBe(100)
        ->and($converted['height'])->toBe(80)
        ->and($image)->not->toBeFalse();

    imagedestroy($image);
});

test('it downscales images whose long edge is over 1920 pixels', function () {
    $png = fakePng(2400, 1200);

    $converted = (new ConvertImageToWebp)->handle($png);

    expect($converted['width'])->toBe(1920)
        ->and($converted['height'])->toBe(960);
});

test('it can skip resizing', function () {
    $png = fakePng(2400, 1200);

    $converted = (new ConvertImageToWebp)->handle($png, maxLongEdge: null);

    expect($converted['width'])->toBe(2400)
        ->and($converted['height'])->toBe(1200);
});

function fakePng(int $width, int $height): string
{
    $image = imagecreatetruecolor($width, $height);
    imagefilledrectangle($image, 0, 0, $width, $height, imagecolorallocate($image, 255, 0, 0));
    ob_start();
    imagepng($image);
    $contents = ob_get_clean();
    imagedestroy($image);

    expect($contents)->toBeString();

    return $contents;
}
