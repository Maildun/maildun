import { HugeiconsIcon } from '@hugeicons/react';
import type { IconSvgElement } from '@hugeicons/react';
import { cva } from 'class-variance-authority';
import type { VariantProps } from 'class-variance-authority';
import type { ComponentProps } from 'react';
import { cn } from '@/lib/utils';

const calloutVariants = cva(
    '@container/callout flex w-full items-start gap-3 rounded-xl border p-4 text-sm text-foreground',
    {
        variants: {
            variant: {
                secondary:
                    'border-border/60 bg-muted/50 [&>svg]:text-muted-foreground',
                success: 'border-success/20 bg-success/5 [&>svg]:text-success',
                warning: 'border-warning/20 bg-warning/5 [&>svg]:text-warning',
                danger: 'border-destructive/20 bg-destructive/5 text-destructive [&>svg]:text-destructive [&_[data-slot=callout-text]]:text-destructive [&_[data-slot=callout-text]_a]:text-destructive',
            },
        },
        defaultVariants: {
            variant: 'secondary',
        },
    },
);

function Callout({
    className,
    variant = 'secondary',
    icon,
    inline = false,
    children,
    ...props
}: ComponentProps<'div'> &
    VariantProps<typeof calloutVariants> & {
        icon?: IconSvgElement;
        inline?: boolean;
    }) {
    return (
        <div
            data-slot="callout"
            data-variant={variant}
            className={cn(calloutVariants({ variant }), className)}
            {...props}
        >
            {icon ? (
                <HugeiconsIcon
                    icon={icon}
                    className="mt-0.5 size-5 shrink-0"
                    aria-hidden
                />
            ) : null}
            <div
                className={cn(
                    'flex min-w-0 flex-1 flex-col gap-3',
                    inline &&
                        '@lg/callout:flex-row @lg/callout:items-start @lg/callout:gap-6',
                )}
            >
                {children}
            </div>
        </div>
    );
}

function CalloutContent({ className, ...props }: ComponentProps<'div'>) {
    return (
        <div
            data-slot="callout-content"
            className={cn('flex min-w-0 flex-1 flex-col gap-1', className)}
            {...props}
        />
    );
}

function CalloutHeading({ className, ...props }: ComponentProps<'div'>) {
    return (
        <div
            data-slot="callout-heading"
            className={cn('font-medium', className)}
            {...props}
        />
    );
}

function CalloutText({ className, ...props }: ComponentProps<'div'>) {
    return (
        <div
            data-slot="callout-text"
            className={cn(
                'text-sm/relaxed text-pretty text-muted-foreground [&_a]:font-medium [&_a]:text-foreground [&_a]:underline [&_a]:underline-offset-3 [&_p:not(:last-child)]:mb-3',
                className,
            )}
            {...props}
        />
    );
}

function CalloutActions({ className, ...props }: ComponentProps<'div'>) {
    return (
        <div
            data-slot="callout-actions"
            className={cn(
                'flex shrink-0 flex-wrap items-center gap-2',
                className,
            )}
            {...props}
        />
    );
}

export { Callout, CalloutContent, CalloutHeading, CalloutText, CalloutActions };
