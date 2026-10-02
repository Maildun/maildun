import { InformationCircleIcon } from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import type { IconSvgElement } from '@hugeicons/react';
import { Card, CardContent } from '@/components/ui/card';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';

export function MetricCard({
    label,
    value,
    detail,
    hint,
    icon,
    iconClassName,
}: {
    label: string;
    value: string;
    detail: string;
    /** What the metric counts, shown in a tooltip next to the label. */
    hint?: string;
    icon?: IconSvgElement;
    iconClassName?: string;
}) {
    return (
        <Card className="gap-0 py-0">
            <CardContent className="flex flex-col gap-3">
                <div className="flex items-start justify-between gap-3">
                    <div className="flex min-w-0 flex-col gap-2">
                        <p className="flex items-center gap-1 text-sm text-muted-foreground">
                            {label}
                            {hint ? (
                                <Tooltip>
                                    <TooltipTrigger
                                        render={
                                            <button
                                                type="button"
                                                aria-label={`About ${label}`}
                                                className="text-muted-foreground hover:text-foreground"
                                            />
                                        }
                                    >
                                        <HugeiconsIcon
                                            icon={InformationCircleIcon}
                                            className="size-3.5"
                                            aria-hidden
                                        />
                                    </TooltipTrigger>
                                    <TooltipContent className="max-w-64">
                                        {hint}
                                    </TooltipContent>
                                </Tooltip>
                            ) : null}
                        </p>
                        <p className="font-heading text-2xl font-semibold tabular-nums">
                            {value}
                        </p>
                    </div>
                    {icon ? (
                        <div
                            className={cn(
                                'grid size-8 shrink-0 place-items-center rounded-lg',
                                iconClassName,
                            )}
                        >
                            <HugeiconsIcon
                                icon={icon}
                                className="size-4"
                                aria-hidden
                            />
                        </div>
                    ) : null}
                </div>
                <p className="text-xs text-muted-foreground">{detail}</p>
            </CardContent>
        </Card>
    );
}
