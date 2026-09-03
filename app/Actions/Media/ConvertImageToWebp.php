<?php

namespace App\Actions\Media;

use GdImage;
use RuntimeException;

class ConvertImageToWebp
{
    /**
     * Encode image bytes as WebP, optionally capping the long edge.
     *
     * @return array{contents: string, width: int, height: int}
     */
    public function handle(string $contents, ?int $maxLongEdge = 1920, int $quality = 75): array
    {
        $source = imagecreatefromstring($contents);

        if ($source === false) {
            throw new RuntimeException('Unable to read the uploaded image.');
        }

        if (function_exists('imagepalettetotruecolor')) {
            imagepalettetotruecolor($source);
        }

        $width = imagesx($source);
        $height = imagesy($source);

        if ($maxLongEdge !== null && $maxLongEdge > 0) {
            $longest = max($width, $height);

            if ($longest > $maxLongEdge) {
                $scale = $maxLongEdge / $longest;
                $newWidth = (int) round($width * $scale);
                $newHeight = (int) round($height * $scale);

                if ($newWidth < 1 || $newHeight < 1) {
                    imagedestroy($source);

                    throw new RuntimeException('Unable to resize the uploaded image.');
                }

                $source = $this->resize($source, $width, $height, $newWidth, $newHeight);
                $width = $newWidth;
                $height = $newHeight;
            }
        }

        imagealphablending($source, true);
        imagesavealpha($source, true);

        ob_start();
        $wasEncoded = imagewebp($source, null, $quality);
        $encoded = ob_get_clean();
        imagedestroy($source);

        if (! $wasEncoded || ! is_string($encoded) || $encoded === '') {
            throw new RuntimeException('Unable to convert the uploaded image to WebP.');
        }

        return [
            'contents' => $encoded,
            'width' => $width,
            'height' => $height,
        ];
    }

    private function resize(GdImage $source, int $width, int $height, int $newWidth, int $newHeight): GdImage
    {
        /** @var int<1, max> $safeWidth */
        $safeWidth = max(1, $newWidth);
        /** @var int<1, max> $safeHeight */
        $safeHeight = max(1, $newHeight);

        $resized = imagecreatetruecolor($safeWidth, $safeHeight);

        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);

        if ($transparent === false) {
            imagedestroy($resized);
            imagedestroy($source);

            throw new RuntimeException('Unable to preserve image transparency.');
        }

        imagefilledrectangle($resized, 0, 0, $safeWidth, $safeHeight, $transparent);
        imagecopyresampled($resized, $source, 0, 0, 0, 0, $safeWidth, $safeHeight, $width, $height);
        imagedestroy($source);

        return $resized;
    }
}
