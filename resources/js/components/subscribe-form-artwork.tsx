import type { DotBackgroundPattern } from '@/components/subscribe-form-dot-background';
import { SubscribeFormDotBackground } from '@/components/subscribe-form-dot-background';
import { cn } from '@/lib/utils';
import type { SubscribeFormArtworkPreset } from '@/types/audiences';
import type { TeamBrandColor, TeamBrandTheme } from '@/types/teams';

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

function backgroundPresetPattern(
    preset: SubscribeFormArtworkPreset,
): DotBackgroundPattern {
    if (preset === 'background-grid') {
        return {
            cellSize: 14,
            scale: 2.2,
            speed: 1.2,
            contrast: 2.2,
            shape: 'cross',
        };
    }

    if (preset === 'background-orbit') {
        return {
            cellSize: 11,
            scale: 0.8,
            speed: 0.9,
            contrast: 1.8,
            shape: 'round',
            orbit: 1,
        };
    }

    if (preset === 'background-glow') {
        return {
            cellSize: 5,
            scale: 3.2,
            speed: 1.1,
            contrast: 1.4,
            shape: 'round',
            haze: 0.5,
        };
    }

    return {
        cellSize: 4,
        scale: 1.5,
        speed: 0.65,
        contrast: 1.5,
        shape: 'square',
    };
}

/**
 * Brand-colored animated background for a subscribe form's artwork panel:
 * a gradient base, the dot-matrix field for the chosen preset, and a fade.
 */
export function SubscribeFormArtworkVisual({
    preset,
    theme,
    className,
}: {
    preset: SubscribeFormArtworkPreset;
    theme: TeamBrandTheme;
    className?: string;
}) {
    const primary = brandArtworkColors[theme.color];
    const palette = createArtworkPalette(primary);
    const baseGradient = `linear-gradient(145deg, ${palette.deep} 5%, ${palette.dark} 40%, ${palette.primary} 72%, ${palette.light})`;
    const overlayGradient = `linear-gradient(to bottom, ${palette.deep}f2 0%, ${palette.dark}45 40%, transparent 65%, ${palette.highlight} 100%)`;

    return (
        <div
            aria-hidden="true"
            data-artwork-preset={preset}
            data-artwork-animation="dot-background"
            className={cn(
                'pointer-events-none relative size-full overflow-hidden',
                className,
            )}
            style={{
                backgroundColor: palette.deep,
                backgroundImage: baseGradient,
            }}
        >
            <SubscribeFormDotBackground
                pattern={backgroundPresetPattern(preset)}
                palette={[
                    palette.deep,
                    palette.dark,
                    palette.light,
                    palette.highlight,
                ]}
            />
            <div
                className="absolute inset-0"
                style={{ backgroundImage: overlayGradient }}
            />
        </div>
    );
}
