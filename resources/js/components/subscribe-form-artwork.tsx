import type { CSSProperties } from 'react';
import { cn } from '@/lib/utils';
import type { SubscribeFormArtworkPreset } from '@/types/audiences';
import type { TeamBrandColor, TeamBrandTheme } from '@/types/teams';
import DottedBackground from '../../../components/originkit/ui/hero-26/dotmatrix';

/** Fixed artwork keyed by image preset value; none ship at the moment. */
const imagePresetStyles: Record<string, CSSProperties> = {};

const brandArtworkColors: Record<TeamBrandColor, string> = {
    blue: '#3b82f6',
    indigo: '#6366f1',
    violet: '#8b5cf6',
    purple: '#a855f7',
    fuchsia: '#d946ef',
    pink: '#ec4899',
    rose: '#f43f5e',
    red: '#ef4444',
    orange: '#f97316',
    amber: '#f59e0b',
    lime: '#84cc16',
    emerald: '#10b981',
    teal: '#14b8a6',
    cyan: '#06b6d4',
    neutral: '#737373',
};

type ArtworkPalette = {
    deep: string;
    dark: string;
    primary: string;
    light: string;
    highlight: string;
};

function mixHexColors(color: string, target: string, amount: number): string {
    const sourceChannels = color
        .slice(1)
        .match(/.{2}/g)!
        .map((channel) => Number.parseInt(channel, 16));
    const targetChannels = target
        .slice(1)
        .match(/.{2}/g)!
        .map((channel) => Number.parseInt(channel, 16));

    return `#${sourceChannels
        .map((channel, index) =>
            Math.round(channel + (targetChannels[index] - channel) * amount)
                .toString(16)
                .padStart(2, '0'),
        )
        .join('')}`;
}

function createArtworkPalette(primary: string): ArtworkPalette {
    return {
        deep: mixHexColors(primary, '#020617', 0.88),
        dark: mixHexColors(primary, '#020617', 0.42),
        primary,
        light: mixHexColors(primary, '#ffffff', 0.48),
        highlight: '#ffffff',
    };
}

type AnimatedBackgroundOptions = {
    frequency: number;
    speed: number;
    cellSize: number;
    gamma: number;
    paletteBias: number;
    useGlyphAtlas?: boolean;
    characters?: string;
    fontSizePx?: number;
};

function backgroundPresetOptions(
    preset: SubscribeFormArtworkPreset,
): AnimatedBackgroundOptions {
    if (preset === 'background-grid') {
        return {
            frequency: 2.2,
            speed: 1.4,
            cellSize: 6,
            gamma: 4,
            paletteBias: 2,
            useGlyphAtlas: true,
            characters: '+·',
            fontSizePx: 18,
        };
    }

    if (preset === 'background-orbit') {
        return {
            frequency: 0.8,
            speed: 0.9,
            cellSize: 11,
            gamma: 2.2,
            paletteBias: 1,
        };
    }

    if (preset === 'background-glow') {
        return {
            frequency: 3.5,
            speed: 1.2,
            cellSize: 5,
            gamma: 1.6,
            paletteBias: 2,
        };
    }

    return {
        frequency: 1.5,
        speed: 0.65,
        cellSize: 1,
        gamma: 3,
        paletteBias: 3,
    };
}

export function SubscribeFormArtworkVisual({
    preset,
    theme,
    className,
}: {
    preset: SubscribeFormArtworkPreset;
    theme: TeamBrandTheme;
    className?: string;
}) {
    const imageStyle = imagePresetStyles[preset];

    if (!imageStyle) {
        const primary = brandArtworkColors[theme.color];
        const palette = createArtworkPalette(primary);
        const options = backgroundPresetOptions(preset);
        const baseGradient = `linear-gradient(145deg, ${palette.deep} 5%, ${palette.dark} 40%, ${palette.primary} 72%, ${palette.light})`;
        const overlayGradient = `linear-gradient(to bottom, ${palette.deep}f2 0%, ${palette.dark}45 40%, transparent 65%, ${palette.highlight} 100%)`;

        return (
            <div
                aria-hidden="true"
                data-artwork-preset={preset}
                data-artwork-animation="originkit-hero-26"
                className={cn(
                    'pointer-events-none relative size-full overflow-hidden',
                    className,
                )}
                style={{
                    backgroundColor: palette.deep,
                    backgroundImage: baseGradient,
                }}
            >
                <DottedBackground
                    bgColor="transparent"
                    colors={[
                        palette.deep,
                        palette.dark,
                        palette.light,
                        palette.highlight,
                    ]}
                    style={{ position: 'absolute', inset: 0 }}
                    {...options}
                />
                <div
                    className="absolute inset-0"
                    style={{ backgroundImage: overlayGradient }}
                />
            </div>
        );
    }

    return (
        <div
            aria-hidden="true"
            data-artwork-preset={preset}
            className={cn('size-full bg-cover bg-center', className)}
            style={imageStyle}
        />
    );
}
