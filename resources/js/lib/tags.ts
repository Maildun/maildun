import type { VariantProps } from 'class-variance-authority';
import type { badgeVariants } from '@/components/ui/badge';

export type BadgeVariant = NonNullable<
    VariantProps<typeof badgeVariants>['variant']
>;

/** The curated palette in App\Models\Tag::COLORS, keyed by its stored hex value. */
const TAG_COLORS: Record<string, { label: string; variant: BadgeVariant }> = {
    '#f43f5e': { label: 'Rose', variant: 'rose' },
    '#f97316': { label: 'Orange', variant: 'orange' },
    '#eab308': { label: 'Yellow', variant: 'secondary' },
    '#22c55e': { label: 'Green', variant: 'success' },
    '#14b8a6': { label: 'Teal', variant: 'teal' },
    '#0ea5e9': { label: 'Sky', variant: 'sky' },
    '#6366f1': { label: 'Indigo', variant: 'info' },
    '#a855f7': { label: 'Purple', variant: 'purple' },
    '#ec4899': { label: 'Pink', variant: 'pink' },
};

export function tagColorLabel(color: string | null): string {
    if (!color) {
        return 'No color';
    }

    return TAG_COLORS[color.toLowerCase()]?.label ?? color;
}

/**
 * Badge variant for a tag color. Colors outside the palette (only reachable by
 * posting a custom hex) fall back to the neutral badge.
 */
export function tagBadgeVariant(color: string | null): BadgeVariant {
    if (!color) {
        return 'default';
    }

    return TAG_COLORS[color.toLowerCase()]?.variant ?? 'default';
}
