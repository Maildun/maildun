import { useState } from 'react';
import { CartesianGrid, Line, LineChart, XAxis } from 'recharts';
import {
    SlidingUnderlineList,
    slidingUnderlineInactiveClassName,
} from '@/components/sliding-underline-list';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    ChartContainer,
    ChartTooltip,
    ChartTooltipContent,
} from '@/components/ui/chart';
import type { ChartConfig } from '@/components/ui/chart';
import { Skeleton } from '@/components/ui/skeleton';
import { useMounted } from '@/hooks/use-mounted';
import { cn } from '@/lib/utils';
import type {
    AudienceSubscriberSeriesPoint,
    AudienceSubscriberStatKey,
    AudienceSubscriberStats,
} from '@/types/audiences';

const CHART_CONFIG = {
    current: { label: 'Current', color: 'var(--primary)' },
    previous: { label: 'Previous', color: 'var(--muted-foreground)' },
} satisfies ChartConfig;

const METRICS: {
    key: AudienceSubscriberStatKey;
    label: string;
    seriesKey: keyof AudienceSubscriberSeriesPoint;
    previousKey: keyof AudienceSubscriberSeriesPoint;
}[] = [
    {
        key: 'total',
        label: 'Total',
        seriesKey: 'total',
        previousKey: 'previous_total',
    },
    {
        key: 'subscribed',
        label: 'Subscribed',
        seriesKey: 'subscribed',
        previousKey: 'previous_subscribed',
    },
    {
        key: 'unsubscribed',
        label: 'Unsubscribed',
        seriesKey: 'unsubscribed',
        previousKey: 'previous_unsubscribed',
    },
    {
        key: 'new_this_week',
        label: 'New this week',
        seriesKey: 'new',
        previousKey: 'previous_new',
    },
    {
        key: 'subscribe_rate',
        label: 'Subscribe rate',
        seriesKey: 'subscribe_rate',
        previousKey: 'previous_subscribe_rate',
    },
];

export function SubscriberStatsChart({
    stats,
}: {
    stats: AudienceSubscriberStats;
}) {
    const mounted = useMounted();
    const [activeKey, setActiveKey] =
        useState<AudienceSubscriberStatKey>('total');
    const activeMetric = METRICS.find((metric) => metric.key === activeKey);

    if (!activeMetric) {
        return null;
    }

    const chartData = stats.series.map((point) => ({
        date: point.date,
        current: Number(point[activeMetric.seriesKey]),
        previous: Number(point[activeMetric.previousKey]),
    }));

    return (
        <Card className="py-0" data-test="subscriber-stats-chart">
            <CardHeader className="flex flex-col items-stretch p-0 sm:flex-row">
                <CardTitle className="sr-only">Contact stats</CardTitle>
                <CardDescription className="sr-only">
                    Contact totals for the last 28 days compared with the
                    previous period
                </CardDescription>
                <SlidingUnderlineList
                    activeKey={activeKey}
                    className="min-w-0 flex-1"
                    aria-label="Contact stats"
                >
                    {METRICS.map((metric) => {
                        const active = metric.key === activeKey;

                        return (
                            <button
                                key={metric.key}
                                type="button"
                                data-active={active}
                                data-test={`subscriber-stat-${metric.key}`}
                                aria-pressed={active}
                                onClick={() => setActiveKey(metric.key)}
                                className={cn(
                                    'relative flex min-w-36 flex-1 flex-col gap-1 px-4 py-3 text-left transition-colors duration-300 ease-out focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none motion-reduce:transition-none',
                                    !active &&
                                        slidingUnderlineInactiveClassName,
                                )}
                            >
                                <span className="text-xs text-muted-foreground">
                                    {metric.label}
                                </span>
                                <span className="flex items-baseline justify-between gap-2">
                                    <span className="text-xl font-semibold tabular-nums">
                                        {formatMetricValue(
                                            metric.key,
                                            stats[metric.key].value,
                                        )}
                                    </span>
                                    <MetricChange
                                        change={stats[metric.key].change}
                                        invert={metric.key === 'unsubscribed'}
                                    />
                                </span>
                            </button>
                        );
                    })}
                </SlidingUnderlineList>
            </CardHeader>
            <CardContent className="px-2 pt-4 pb-4 sm:px-6">
                {mounted ? (
                    <ChartContainer
                        config={CHART_CONFIG}
                        className="aspect-auto h-40 w-full"
                    >
                        <LineChart
                            accessibilityLayer
                            data={chartData}
                            margin={{ left: 12, right: 12 }}
                        >
                            <CartesianGrid vertical={false} />
                            <XAxis
                                dataKey="date"
                                tickLine={false}
                                axisLine={false}
                                tickMargin={8}
                                minTickGap={32}
                                tickFormatter={formatTickDate}
                            />
                            <ChartTooltip
                                content={
                                    <ChartTooltipContent
                                        labelFormatter={(value) =>
                                            formatTooltipDate(String(value))
                                        }
                                    />
                                }
                            />
                            <Line
                                dataKey="previous"
                                type="monotone"
                                stroke="var(--color-previous)"
                                strokeWidth={2}
                                strokeDasharray="4 4"
                                dot={false}
                                isAnimationActive={false}
                            />
                            <Line
                                dataKey="current"
                                type="monotone"
                                stroke="var(--color-current)"
                                strokeWidth={2}
                                dot={false}
                                isAnimationActive={false}
                            />
                        </LineChart>
                    </ChartContainer>
                ) : (
                    <Skeleton className="h-40 w-full" />
                )}
            </CardContent>
        </Card>
    );
}

function MetricChange({
    change,
    invert,
}: {
    change: number | null;
    invert: boolean;
}) {
    if (change === null) {
        return (
            <span className="text-xs text-muted-foreground tabular-nums">
                —
            </span>
        );
    }

    const improved = invert ? change < 0 : change > 0;
    const declined = invert ? change > 0 : change < 0;

    return (
        <span
            className={cn(
                'text-xs font-medium tabular-nums',
                improved && 'text-success',
                declined && 'text-destructive',
                change === 0 && 'text-muted-foreground',
            )}
        >
            {formatChange(change)}
        </span>
    );
}

function formatMetricValue(
    key: AudienceSubscriberStatKey,
    value: number,
): string {
    if (key === 'subscribe_rate') {
        return `${Number.isInteger(value) ? value.toFixed(0) : value.toFixed(1)}%`;
    }

    return value.toLocaleString();
}

function formatChange(change: number): string {
    const absolute = Math.abs(change);
    const formatted = Number.isInteger(absolute)
        ? String(absolute)
        : absolute.toFixed(1);
    const sign = change > 0 ? '+' : change < 0 ? '-' : '';

    return `${sign}${formatted}%`;
}

function formatTickDate(value: string): string {
    return new Date(`${value}T00:00:00`).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
    });
}

function formatTooltipDate(value: string): string {
    return new Date(`${value}T00:00:00`).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}
