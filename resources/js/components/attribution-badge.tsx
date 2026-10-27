/**
 * The "Powered by Maildun" credit Maildun shows by default. LICENSE does not
 * require it: term 1 of the additional terms only asks that the author
 * attribution stay in the Appropriate Legal Notices on the About page.
 *
 * It links to config('attribution.source_url'), so it doubles as the source
 * offer that section 13 of the GNU Affero General Public License requires of
 * anyone running a modified version for users over a network. Such a version
 * should point that URL at its own source, and needs another way to offer
 * that source if it drops the badge.
 */
import { usePage } from '@inertiajs/react';
import { AppLogoWordmark } from '@/components/app-logo-icon';
import { cn } from '@/lib/utils';

const PRODUCT_NAME = 'Maildun';
const FALLBACK_SOURCE_URL = 'https://github.com/Maildun/maildun';

type Props = {
    className?: string;
};

export function AttributionBadge({ className }: Props) {
    const sourceUrl =
        usePage().props.attribution?.sourceUrl ?? FALLBACK_SOURCE_URL;

    return (
        <p
            className={cn(
                'flex items-center justify-center gap-1.5 text-center text-xs text-muted-foreground',
                className,
            )}
            data-test="attribution-badge"
        >
            Powered by
            <a
                href={sourceUrl}
                target="_blank"
                rel="noreferrer"
                className="inline-flex items-center"
            >
                <AppLogoWordmark label={PRODUCT_NAME} className="text-xs" />
            </a>
        </p>
    );
}
