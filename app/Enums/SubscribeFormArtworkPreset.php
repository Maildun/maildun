<?php

namespace App\Enums;

enum SubscribeFormArtworkPreset: string
{
    case BackgroundMatrix = 'background-matrix';
    case BackgroundGrid = 'background-grid';
    case BackgroundOrbit = 'background-orbit';
    case BackgroundGlow = 'background-glow';

    public function label(): string
    {
        return match ($this) {
            self::BackgroundMatrix => 'Matrix',
            self::BackgroundGrid => 'Grid',
            self::BackgroundOrbit => 'Orbit',
            self::BackgroundGlow => 'Glow',
        };
    }

    public function artworkType(): SubscribeFormArtworkType
    {
        return match ($this) {
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
