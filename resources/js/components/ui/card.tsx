import * as React from 'react';

import { cn } from '@/lib/utils';

const CardContentContext = React.createContext(false);
const CardBodyContext = React.createContext<'inset' | 'flush' | 'seamless'>(
    'inset',
);

function useCardContent() {
    return React.useContext(CardContentContext);
}

function Card({
    className,
    size = 'default',
    body = 'inset',
    ...props
}: React.ComponentProps<'div'> & {
    size?: 'default' | 'sm';
    body?: 'inset' | 'flush' | 'seamless';
}) {
    return (
        <CardBodyContext.Provider value={body}>
            <CardContentContext.Provider value={false}>
                <div
                    data-slot="card"
                    data-size={size}
                    data-body={body}
                    className={cn(
                        'group/card flex min-w-0 flex-col gap-4 overflow-hidden rounded-2xl border border-border/60 bg-muted py-(--card-spacing) text-sm text-card-foreground [--card-spacing:--spacing(5)] has-[>[data-slot=card-content]:first-child]:pt-1 has-[>[data-slot=card-content]:last-child]:pb-1 has-[>img:first-child]:pt-0 data-[body=seamless]:bg-card data-[body=seamless]:py-(--card-spacing) data-[body=seamless]:shadow-xs data-[size=sm]:gap-3 data-[size=sm]:[--card-spacing:--spacing(4)] *:[img:first-child]:rounded-t-2xl *:[img:last-child]:rounded-b-2xl',
                        className,
                    )}
                    {...props}
                />
            </CardContentContext.Provider>
        </CardBodyContext.Provider>
    );
}

function CardHeader({ className, ...props }: React.ComponentProps<'div'>) {
    return (
        <div
            data-slot="card-header"
            className={cn(
                'group/card-header @container/card-header grid auto-rows-min items-start gap-1.5 px-(--card-spacing) has-data-[slot=card-action]:grid-cols-[1fr_auto] has-data-[slot=card-description]:grid-rows-[auto_auto] [.border-b]:pb-(--card-spacing)',
                className,
            )}
            {...props}
        />
    );
}

function CardTitle({ className, ...props }: React.ComponentProps<'div'>) {
    return (
        <div
            data-slot="card-title"
            className={cn(
                'font-heading text-base leading-snug font-medium group-data-[size=sm]/card:text-sm',
                className,
            )}
            {...props}
        />
    );
}

function CardDescription({ className, ...props }: React.ComponentProps<'div'>) {
    return (
        <div
            data-slot="card-description"
            className={cn('text-sm text-muted-foreground', className)}
            {...props}
        />
    );
}

function CardAction({ className, ...props }: React.ComponentProps<'div'>) {
    return (
        <div
            data-slot="card-action"
            className={cn(
                'col-start-2 row-span-2 row-start-1 self-start justify-self-end',
                className,
            )}
            {...props}
        />
    );
}

function CardContent({ className, ...props }: React.ComponentProps<'div'>) {
    const body = React.useContext(CardBodyContext);

    return (
        <CardContentContext.Provider value={true}>
            <div
                data-slot="card-content"
                className={cn(
                    'min-w-0 px-(--card-spacing) has-[>[data-slot=table-container]:only-child]:p-0',
                    body === 'inset' &&
                        'mx-1 rounded-xl border bg-card p-[calc(var(--card-spacing)-var(--spacing))] shadow-xs',
                    body === 'flush' &&
                        'rounded-xl border-y bg-card py-(--card-spacing)',
                    className,
                )}
                {...props}
            />
        </CardContentContext.Provider>
    );
}

function CardFooter({ className, ...props }: React.ComponentProps<'div'>) {
    return (
        <div
            data-slot="card-footer"
            className={cn(
                'flex flex-wrap items-center gap-3 px-(--card-spacing)',
                className,
            )}
            {...props}
        />
    );
}

export {
    Card,
    CardHeader,
    CardFooter,
    CardTitle,
    CardAction,
    CardDescription,
    CardContent,
    useCardContent,
};
