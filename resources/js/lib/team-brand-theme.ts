import type { CSSProperties } from 'react';
import type { TeamBrandColor, TeamBrandFont, TeamBrandTheme } from '@/types';

export type TeamBrandPalette = {
    light: string;
    dark: string;
    lightForeground: string;
    darkForeground: string;
};

export const teamBrandPalettes: Record<TeamBrandColor, TeamBrandPalette> = {
    blue: {
        light: 'oklch(0.631 0.2 253)',
        dark: 'oklch(0.65 0.19 253)',
        lightForeground: 'oklch(0.985 0 0)',
        darkForeground: 'oklch(0.985 0 0)',
    },
    indigo: {
        light: 'oklch(0.511 0.262 276.966)',
        dark: 'oklch(0.673 0.182 276.935)',
        lightForeground: 'oklch(0.985 0 0)',
        darkForeground: 'oklch(0.18 0.02 277)',
    },
    violet: {
        light: 'oklch(0.58 0.22 292)',
        dark: 'oklch(0.68 0.19 292)',
        lightForeground: 'oklch(0.985 0 0)',
        darkForeground: 'oklch(0.18 0.02 292)',
    },
    purple: {
        light: 'oklch(0.558 0.288 302.321)',
        dark: 'oklch(0.714 0.203 305.504)',
        lightForeground: 'oklch(0.985 0 0)',
        darkForeground: 'oklch(0.18 0.02 302)',
    },
    fuchsia: {
        light: 'oklch(0.591 0.293 322.896)',
        dark: 'oklch(0.74 0.238 322.16)',
        lightForeground: 'oklch(0.985 0 0)',
        darkForeground: 'oklch(0.18 0.02 323)',
    },
    pink: {
        light: 'oklch(0.592 0.249 0.584)',
        dark: 'oklch(0.718 0.202 349.761)',
        lightForeground: 'oklch(0.985 0 0)',
        darkForeground: 'oklch(0.18 0.02 350)',
    },
    rose: {
        light: 'oklch(0.6 0.21 12)',
        dark: 'oklch(0.7 0.18 12)',
        lightForeground: 'oklch(0.985 0 0)',
        darkForeground: 'oklch(0.18 0.02 12)',
    },
    red: {
        light: 'oklch(0.577 0.245 27.325)',
        dark: 'oklch(0.704 0.191 22.216)',
        lightForeground: 'oklch(0.985 0 0)',
        darkForeground: 'oklch(0.18 0.02 27)',
    },
    orange: {
        light: 'oklch(0.646 0.222 41.116)',
        dark: 'oklch(0.75 0.183 55.934)',
        lightForeground: 'oklch(0.18 0.02 41)',
        darkForeground: 'oklch(0.18 0.02 56)',
    },
    amber: {
        light: 'oklch(0.666 0.179 58.318)',
        dark: 'oklch(0.828 0.189 84.429)',
        lightForeground: 'oklch(0.18 0.02 58)',
        darkForeground: 'oklch(0.18 0.02 84)',
    },
    lime: {
        light: 'oklch(0.648 0.2 131.684)',
        dark: 'oklch(0.841 0.238 128.85)',
        lightForeground: 'oklch(0.18 0.02 132)',
        darkForeground: 'oklch(0.18 0.02 129)',
    },
    emerald: {
        light: 'oklch(0.58 0.18 161)',
        dark: 'oklch(0.7 0.16 161)',
        lightForeground: 'oklch(0.985 0 0)',
        darkForeground: 'oklch(0.18 0.02 161)',
    },
    teal: {
        light: 'oklch(0.6 0.118 184.704)',
        dark: 'oklch(0.777 0.152 181.912)',
        lightForeground: 'oklch(0.985 0 0)',
        darkForeground: 'oklch(0.18 0.02 182)',
    },
    cyan: {
        light: 'oklch(0.609 0.126 221.723)',
        dark: 'oklch(0.789 0.154 211.53)',
        lightForeground: 'oklch(0.18 0.02 222)',
        darkForeground: 'oklch(0.18 0.02 212)',
    },
    neutral: {
        light: 'oklch(0.32 0.02 260)',
        dark: 'oklch(0.78 0.02 260)',
        lightForeground: 'oklch(0.985 0 0)',
        darkForeground: 'oklch(0.18 0.02 260)',
    },
};

export const teamBrandFonts: Record<TeamBrandFont, string> = {
    inter: "'Inter Variable', sans-serif",
    'instrument-sans':
        "'Instrument Sans', ui-sans-serif, system-ui, sans-serif",
    'system-sans': 'ui-sans-serif, system-ui, sans-serif',
    'rounded-sans': 'ui-rounded, "SF Pro Rounded", system-ui, sans-serif',
    'humanist-sans': 'Optima, Candara, "Noto Sans", Arial, sans-serif',
    serif: 'ui-serif, Georgia, serif',
    georgia: 'Georgia, Cambria, "Times New Roman", serif',
    mono: 'ui-monospace, SFMono-Regular, Menlo, monospace',
};

export function subscribeFormThemeStyle(theme: TeamBrandTheme): CSSProperties {
    const palette = teamBrandPalettes[theme.color] ?? teamBrandPalettes.blue;

    return {
        '--subscribe-form-primary-light': palette.light,
        '--subscribe-form-primary-dark': palette.dark,
        '--subscribe-form-primary-foreground-light': palette.lightForeground,
        '--subscribe-form-primary-foreground-dark': palette.darkForeground,
        '--font-sans': teamBrandFonts[theme.font] ?? teamBrandFonts.inter,
    } as CSSProperties;
}
