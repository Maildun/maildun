import {
    AppleIcon,
    BrowserIcon,
    ChromeIcon,
    Mail01Icon,
    MicrosoftIcon,
    Robot01Icon,
    SafariIcon,
    Shield01Icon,
} from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';

import { BRAND_ICON_PATHS, BRAND_ICON_VIEW_BOX } from '@/lib/brand-icon-paths';
import type { BrandIconName } from '@/lib/brand-icon-paths';
import { cn } from '@/lib/utils';

type ClientIcon = { brand: BrandIconName } | { icon: typeof BrowserIcon };

/**
 * Client labels come from a closed list in ClassifyEmailTrackingEvent, so the
 * first matching keyword wins. Edge, Outlook, and Yahoo have no public-domain
 * brand glyph, so they fall back to the vendor or a generic browser mark.
 */
const CLIENT_ICONS: Array<[string, ClientIcon]> = [
    ['chrome', { icon: ChromeIcon }],
    ['firefox', { brand: 'Firefox' }],
    ['safari', { icon: SafariIcon }],
    ['thunderbird', { brand: 'Thunderbird' }],
    ['gmail', { brand: 'Gmail' }],
    ['apple', { icon: AppleIcon }],
    ['outlook', { icon: MicrosoftIcon }],
    ['yahoo', { icon: Mail01Icon }],
    ['scanner', { icon: Shield01Icon }],
    ['automated', { icon: Robot01Icon }],
];

export function ClientIcon({
    label,
    className,
}: {
    label: string;
    className?: string;
}) {
    const normalizedLabel = label.toLowerCase();
    const match = CLIENT_ICONS.find(([keyword]) =>
        normalizedLabel.includes(keyword),
    )?.[1];

    if (match && 'brand' in match) {
        return (
            <svg
                viewBox={BRAND_ICON_VIEW_BOX}
                className={cn(
                    'size-3.5 shrink-0 fill-current text-muted-foreground',
                    className,
                )}
                aria-hidden="true"
            >
                <path d={BRAND_ICON_PATHS[match.brand].path} />
            </svg>
        );
    }

    return (
        <HugeiconsIcon
            icon={match ? match.icon : BrowserIcon}
            className={cn('size-4 shrink-0 text-muted-foreground', className)}
            aria-hidden="true"
        />
    );
}
