import { cn } from '@/lib/utils';

export function MetricGauge({
    value,
    label,
    detail,
    className,
    colorClassName = 'text-success',
}: {
    value: number | null;
    label: string;
    detail: string;
    className?: string;
    colorClassName?: string;
}) {
    const percentage =
        value === null ? null : Math.min(Math.max(value, 0), 100);
    const displayValue = percentage === null ? '—' : `${percentage}%`;

    return (
        <figure
            className={cn(
                'flex w-full max-w-52 flex-col items-center gap-2 text-center',
                className,
            )}
            role="img"
            aria-label={`${label}: ${percentage === null ? 'unavailable' : displayValue}. ${detail}`}
        >
            <div className="relative w-full">
                <svg
                    viewBox="0 0 240 148"
                    className="w-full overflow-visible"
                    fill="none"
                    aria-hidden="true"
                >
                    <path
                        d="M 20 120 A 100 100 0 0 1 220 120"
                        pathLength="100"
                        stroke="currentColor"
                        strokeWidth="16"
                        strokeLinecap="round"
                        className="text-muted"
                    />
                    {percentage !== null && percentage > 0 && (
                        <path
                            d="M 20 120 A 100 100 0 0 1 220 120"
                            pathLength="100"
                            stroke="currentColor"
                            strokeWidth="16"
                            strokeLinecap="round"
                            strokeDasharray={`${percentage} 100`}
                            className={cn(
                                'transition-[stroke-dasharray] duration-300 ease-out motion-reduce:transition-none',
                                colorClassName,
                            )}
                        />
                    )}
                    <g className="fill-muted-foreground text-[11px]">
                        <text x="20" y="144" textAnchor="middle">
                            0
                        </text>
                        <text x="220" y="144" textAnchor="middle">
                            100
                        </text>
                    </g>
                </svg>
                <p className="absolute inset-x-0 bottom-3 font-heading text-2xl font-semibold tracking-tight tabular-nums sm:bottom-5 sm:text-3xl">
                    {displayValue}
                </p>
            </div>
            <figcaption className="flex flex-col gap-1">
                <p className="text-sm font-medium">{label}</p>
                <p className="text-xs text-muted-foreground">{detail}</p>
            </figcaption>
        </figure>
    );
}
