import type { ReactNode } from 'react';
import { GoogleIcon } from '@/components/icons/google-icon';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

type ContinueWithGoogleProps = {
    href: string;
    tabIndex?: number;
    className?: string;
    children?: ReactNode;
};

/**
 * Starts a round trip to Google. The server answers with a redirect to
 * accounts.google.com, so this stays a plain anchor (a full page navigation)
 * rather than an Inertia Link or router visit.
 */
export function ContinueWithGoogle({
    href,
    tabIndex,
    className,
    children = 'Continue with Google',
}: ContinueWithGoogleProps) {
    return (
        <Button
            variant="outline"
            nativeButton={false}
            render={<a href={href} />}
            tabIndex={tabIndex}
            className={cn('w-full', className)}
            data-test="continue-with-google-button"
        >
            <GoogleIcon data-icon="inline-start" />
            {children}
        </Button>
    );
}

/**
 * Separates "Continue with Google" from the email form. The label sits between
 * two rules instead of masking a full-width line, so it works on the auth
 * pages' translucent, grainy background.
 */
export function ContinueWithGoogleDivider({
    label = 'or',
    className,
}: {
    label?: string;
    className?: string;
}) {
    return (
        <div
            className={cn(
                'flex items-center gap-3 text-xs text-muted-foreground',
                className,
            )}
        >
            <span aria-hidden="true" className="h-px flex-1 bg-border" />
            <span>{label}</span>
            <span aria-hidden="true" className="h-px flex-1 bg-border" />
        </div>
    );
}
