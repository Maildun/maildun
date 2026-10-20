<?php

namespace App\Enums;

enum SubscribeFormArtworkPreset: string
{
    case ImagePastel = 'image-pastel';
    case ImageEmber = 'image-ember';
    case ImageGolden = 'image-golden';
    case ImageCumulus = 'image-cumulus';
    case ImageDunes = 'image-dunes';
    case ImageMeadow = 'image-meadow';
    case ImageShore = 'image-shore';
    case BackgroundMatrix = 'background-matrix';
    case BackgroundGrid = 'background-grid';
    case BackgroundOrbit = 'background-orbit';
    case BackgroundGlow = 'background-glow';

    public function label(): string
    {
        return match ($this) {
            self::ImagePastel => 'Pastel',
            self::ImageEmber => 'Ember',
            self::ImageGolden => 'Golden',
            self::ImageCumulus => 'Cumulus',
            self::ImageDunes => 'Dunes',
            self::ImageMeadow => 'Meadow',
            self::ImageShore => 'Shore',
            self::BackgroundMatrix => 'Matrix',
            self::BackgroundGrid => 'Grid',
            self::BackgroundOrbit => 'Orbit',
            self::BackgroundGlow => 'Glow',
        };
    }

    public function artworkType(): SubscribeFormArtworkType
    {
        return match ($this) {
            self::ImagePastel,
            self::ImageEmber,
            self::ImageGolden,
            self::ImageCumulus,
            self::ImageDunes,
            self::ImageMeadow,
            self::ImageShore => SubscribeFormArtworkType::ImagePreset,
            self::BackgroundMatrix,
            self::BackgroundGrid,
            self::BackgroundOrbit,
            self::BackgroundGlow => SubscribeFormArtworkType::BackgroundPreset,
        };
    }

    /**
     * @return list<array{value: string, label: string, artwork_type: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $preset): array => [
            'value' => $preset->value,
            'label' => $preset->label(),
            'artwork_type' => $preset->artworkType()->value,
        ], self::cases());
    }
}
