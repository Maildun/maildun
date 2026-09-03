import type { PropsWithChildren, ReactNode } from 'react';
import { cn } from '@/lib/utils';

type SettingsPanelProps = PropsWithChildren<{
    title?: string;
    description?: string;
    actions?: ReactNode;
    variant?: 'card' | 'inset';
    className?: string;
}>;

export function SettingsPanel({
    title,
    description,
    actions,
    variant = 'card',
    className,
    children,
}: SettingsPanelProps) {
    return (
        <div
            className={cn(
                variant === 'card'
                    ? 'overflow-hidden rounded-2xl border bg-card shadow-xs'
                    : 'grainy relative overflow-hidden rounded-xl bg-muted p-1 shadow-inner',
                className,
            )}
        >
            {title ? (
                <header
                    className={cn(
                        'flex flex-col items-start justify-between gap-x-6 gap-y-3 lg:flex-row lg:items-center',
                        variant === 'card'
                            ? 'border-b px-6 py-5 sm:px-7'
                            : 'px-5 py-4',
                    )}
                >
                    <div className="flex flex-col gap-1">
                        <h2 className="font-heading text-base font-medium tracking-tight">
                            {title}
                        </h2>
                        {description ? (
                            <p className="max-w-2xl text-sm text-muted-foreground">
                                {description}
                            </p>
                        ) : null}
                    </div>
                    {actions ? <div className="shrink-0">{actions}</div> : null}
                </header>
            ) : null}
            {variant === 'inset' ? (
                <div className="rounded-lg border bg-card shadow-xs">
                    {children}
                </div>
            ) : (
                children
            )}
        </div>
    );
}
