import { HugeiconsIcon } from '@hugeicons/react';
import type { IconSvgElement } from '@hugeicons/react';
import { cn } from '@/lib/utils';

/**
 * A null value means the transport never reports this outcome (SMTP is
 * handoff only), so the stat says so instead of showing a misleading 0.
 */
export function HealthStat({
    label,
    value,
    icon,
    iconClassName,
    tone = 'default',
    unavailableLabel = 'Not reported by SMTP',
}: {
    label: string;
    value: number | null;
    icon: IconSvgElement;
    iconClassName: string;
    tone?: 'default' | 'danger';
    unavailableLabel?: string;
}) {
    const isAlert = tone === 'danger' && value !== null && value > 0;

    return (
        <div className="flex items-start gap-3">
            <span
                className={cn(
                    'flex size-8 shrink-0 items-center justify-center rounded-lg',
                    iconClassName,
                )}
            >
                <HugeiconsIcon
                    icon={icon}
                    className="size-4"
                    aria-hidden="true"
                />
            </span>
            <div className="min-w-0">
                <p className="text-xs text-muted-foreground">{label}</p>
                {value === null ? (
                    <p className="pt-1.5 text-sm text-muted-foreground">
                        {unavailableLabel}
                    </p>
                ) : (
                    <p
                        className={cn(
                            'font-heading text-xl font-semibold tabular-nums',
                            isAlert && 'text-destructive',
                        )}
                    >
                        {value.toLocaleString()}
                    </p>
                )}
            </div>
        </div>
    );
}
