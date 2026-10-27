import { Link } from '@inertiajs/react';
import { AttributionBadge } from '@/components/attribution-badge';
import { cn } from '@/lib/utils';
import { about } from '@/routes';

type Props = {
    className?: string;
};

/**
 * The default "Powered by Maildun" credit plus a link to the About page, where
 * Maildun shows its Appropriate Legal Notices. Guest pages such as the auth
 * screens use it because they have no user menu to reach About from.
 */
export function AttributionFooter({ className }: Props) {
    return (
        <footer
            className={cn(
                'flex items-center justify-center gap-2 text-xs text-muted-foreground',
                className,
            )}
            data-test="attribution-footer"
        >
            <AttributionBadge />
            <span aria-hidden="true">·</span>
            <Link
                href={about()}
                className="underline-offset-4 transition-colors duration-200 hover:text-foreground hover:underline"
                data-test="attribution-footer-about-link"
            >
                About
            </Link>
        </footer>
    );
}
