import type { PropsWithChildren, ReactNode } from 'react';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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
        <Card data-variant={variant} className={cn('gap-0 py-1', className)}>
            {title ? (
                <CardHeader className="flex flex-col items-start justify-between gap-x-6 gap-y-3 px-5 py-4 lg:flex-row lg:items-center">
                    <div className="flex flex-col gap-1">
                        <CardTitle>
                            <h2>{title}</h2>
                        </CardTitle>
                        {description ? (
                            <CardDescription className="max-w-2xl">
                                {description}
                            </CardDescription>
                        ) : null}
                    </div>
                    {actions ? <div className="shrink-0">{actions}</div> : null}
                </CardHeader>
            ) : null}
            <CardContent className="overflow-hidden p-0">
                {children}
            </CardContent>
        </Card>
    );
}
