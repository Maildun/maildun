/**
 * Attribution notice required by the additional terms in LICENSE, added under
 * section 7(b) of the GNU Affero General Public License. See LICENSE for the
 * terms that apply to this file.
 *
 * This notice is a license condition, not decoration. It must stay legible and
 * reasonably prominent on the authenticated application and on every hosted
 * page served to the public. Restyling it to suit a surrounding design is
 * allowed; removing it, hiding it, or renaming the product it credits
 * terminates the rights granted by LICENSE. An unbranded deployment needs a
 * separate commercial license from the copyright holder.
 */
import { usePage } from '@inertiajs/react';
import { AppLogoWordmark } from '@/components/app-logo-icon';
import { cn } from '@/lib/utils';

const PRODUCT_NAME = 'Maildun';
const FALLBACK_SOURCE_URL = 'https://github.com/abduns/maildun';

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
