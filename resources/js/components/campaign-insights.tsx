import {
    Clock01Icon,
    ComputerIcon,
    Globe02Icon,
    HelpCircleIcon,
    InformationCircleIcon,
    ServerStack01Icon,
    Shield01Icon,
    SmartPhone01Icon,
    Tablet01Icon,
    WifiLocationIcon,
} from '@hugeicons/core-free-icons';
import type { IconSvgElement } from '@hugeicons/react';
import { HugeiconsIcon } from '@hugeicons/react';
import { useState } from 'react';

import { CampaignInsightsMap } from '@/components/campaign-insights-map';
import type { CampaignInsightMetric } from '@/components/campaign-insights-map';
import { ClientIcon } from '@/components/client-icon';
import { CountryFlag } from '@/components/country-flag';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardAction,
    CardContent,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';

export type CampaignInsightRow = {
    key: string;
    label: string;
    total_opens: number;
    unique_opens: number;
    total_clicks: number;
    unique_clicks: number;
};

export type CampaignTrafficRow = Omit<CampaignInsightRow, 'key'> & {
    classification: 'human' | 'bot' | 'privacy_proxy' | 'unknown';
};

export type CampaignInsightsData = {
    available: boolean;
    human: {
        opened: number;
        clicked: number;
        open_rate: number;
        click_rate: number;
    };
    traffic: CampaignTrafficRow[];
    locations: {
        countries: CampaignInsightRow[];
        regions: CampaignInsightRow[];
        cities: CampaignInsightRow[];
    };
    networks: CampaignInsightRow[];
    clients: CampaignInsightRow[];
    devices: CampaignInsightRow[];
    privacy: {
        raw_ip_stored: false;
        event_retention_days: number;
    };
    attribution: {
        label: string;
        url: string;
    };
};

type InsightRowKind =
    'country' | 'region' | 'city' | 'client' | 'device' | 'network';

const TRAFFIC_DESCRIPTIONS: Record<
    CampaignTrafficRow['classification'],
    string
> = {
    human: 'Recognized browser or mail client',
    bot: 'Security scanner, crawler, or automated client',
    privacy_proxy: 'Remote image proxy or privacy relay',
    unknown: 'Not enough client evidence to classify safely',
};

const TRAFFIC_VARIANTS: Record<
    CampaignTrafficRow['classification'],
    'success' | 'destructive' | 'secondary' | 'default'
> = {
    human: 'success',
    bot: 'destructive',
    privacy_proxy: 'secondary',
    unknown: 'default',
};

export function CampaignInsights({
    insights,
}: {
    insights: CampaignInsightsData;
}) {
    if (!insights.available) {
        return (
            <Card data-test="campaign-insights-empty">
                <CardHeader>
                    <CardTitle>Campaign insights</CardTitle>
                </CardHeader>
                <CardContent className="text-sm text-muted-foreground">
                    Human, traffic-quality, location, and technology insights
                    appear as tracked activity arrives.
                </CardContent>
            </Card>
        );
    }

    return (
        <div className="flex flex-col gap-4" data-test="campaign-insights">
            <div className="grid gap-4 lg:grid-cols-[0.8fr_1.2fr]">
                <HumanEngagementCard insights={insights} />
                <TrafficQualityCard rows={insights.traffic} />
            </div>

            <div className="grid gap-4 lg:grid-cols-2 lg:items-stretch">
                <GeographyCard insights={insights} />
                <TechnologyCard insights={insights} />
            </div>

            <PrivacyCard insights={insights} />
        </div>
    );
}

function HumanEngagementCard({ insights }: { insights: CampaignInsightsData }) {
    return (
        <Card size="sm">
            <CardHeader className="border-b">
                <CardTitle>Human engagement</CardTitle>
            </CardHeader>
            <CardContent className="grid grid-cols-2 divide-x divide-border px-0">
                <InsightMetric
                    label="Human opens"
                    value={`${insights.human.open_rate}%`}
                    detail={`${insights.human.opened.toLocaleString()} recipients`}
                />
                <InsightMetric
                    label="Human clicks"
                    value={`${insights.human.click_rate}%`}
                    detail={`${insights.human.clicked.toLocaleString()} recipients`}
                />
            </CardContent>
        </Card>
    );
}

function TrafficQualityCard({ rows }: { rows: CampaignTrafficRow[] }) {
    const [metric, setMetric] = useState<CampaignInsightMetric>('opens');
    const maximumValue = Math.max(
        ...rows.map((row) => insightMetricValue(row, metric)),
        0,
    );

    return (
        <Card size="sm">
            <CardHeader className="border-b">
                <CardTitle>Traffic quality</CardTitle>
                <CardAction>
                    <MetricToggle metric={metric} onChange={setMetric} />
                </CardAction>
            </CardHeader>
            <CardContent>
                <div className="flex flex-col gap-1" role="list">
                    {rows.map((row) => (
                        <div
                            key={row.classification}
                            className="relative overflow-hidden rounded-md"
                            role="listitem"
                            title={TRAFFIC_DESCRIPTIONS[row.classification]}
                        >
                            <InsightBar
                                width={barWidth(
                                    insightMetricValue(row, metric),
                                    maximumValue,
                                )}
                            />
                            <div className="relative flex min-h-9 items-center gap-3 px-3 py-2">
                                <Badge
                                    variant={trafficVariant(row.classification)}
                                >
                                    {row.label}
                                </Badge>
                                <span className="min-w-0 flex-1 truncate text-xs text-muted-foreground">
                                    {TRAFFIC_DESCRIPTIONS[row.classification]}
                                </span>
                                <span className="font-medium tabular-nums">
                                    {insightMetricValue(
                                        row,
                                        metric,
                                    ).toLocaleString()}
                                </span>
                            </div>
                        </div>
                    ))}
                </div>
            </CardContent>
        </Card>
    );
}

function GeographyCard({ insights }: { insights: CampaignInsightsData }) {
    const [metric, setMetric] = useState<CampaignInsightMetric>('opens');

    return (
        <Tabs defaultValue="map" className="h-full min-h-0 gap-0">
            <Card size="sm" className="h-full min-h-[24rem]">
                <CardHeader className="border-b">
                    <CardTitle>Geography</CardTitle>
                    <CardAction>
                        <MetricToggle metric={metric} onChange={setMetric} />
                    </CardAction>
                    <TabsList
                        variant="sliding"
                        className="col-span-full mt-1 max-w-full justify-start"
                        aria-label="Geography view"
                        data-test="campaign-insights-geography-tabs"
                    >
                        <TabsTrigger value="map">Map</TabsTrigger>
                        <TabsTrigger value="country">Country</TabsTrigger>
                        <TabsTrigger value="region">Region</TabsTrigger>
                        <TabsTrigger value="city">City</TabsTrigger>
                    </TabsList>
                </CardHeader>
                <CardContent className="flex min-h-0 flex-1 flex-col">
                    <TabsContent
                        value="map"
                        className="flex min-h-0 flex-1 flex-col"
                    >
                        <CampaignInsightsMap
                            countries={insights.locations.countries}
                            metric={metric}
                        />
                    </TabsContent>
                    <TabsContent
                        value="country"
                        className="flex min-h-0 flex-1 flex-col"
                    >
                        <InsightBarList
                            rows={insights.locations.countries}
                            metric={metric}
                            kind="country"
                        />
                    </TabsContent>
                    <TabsContent
                        value="region"
                        className="flex min-h-0 flex-1 flex-col"
                    >
                        <InsightBarList
                            rows={insights.locations.regions}
                            metric={metric}
                            kind="region"
                        />
                    </TabsContent>
                    <TabsContent
                        value="city"
                        className="flex min-h-0 flex-1 flex-col"
                    >
                        <InsightBarList
                            rows={insights.locations.cities}
                            metric={metric}
                            kind="city"
                        />
                    </TabsContent>
                </CardContent>
                <CardFooter className="justify-between gap-3 text-xs text-muted-foreground">
                    <span>Confidently classified human activity</span>
                    <span className="shrink-0">
                        Geo data by{' '}
                        <a
                            href={insights.attribution.url}
                            target="_blank"
                            rel="noreferrer"
                            className="font-medium text-foreground underline-offset-4 hover:underline"
                        >
                            {insights.attribution.label}
                        </a>
                    </span>
                </CardFooter>
            </Card>
        </Tabs>
    );
}

function TechnologyCard({ insights }: { insights: CampaignInsightsData }) {
    const [metric, setMetric] = useState<CampaignInsightMetric>('opens');

    return (
        <Tabs defaultValue="client" className="h-full min-h-0 gap-0">
            <Card size="sm" className="h-full min-h-[24rem]">
                <CardHeader className="border-b">
                    <CardTitle>Technology and networks</CardTitle>
                    <CardAction>
                        <MetricToggle metric={metric} onChange={setMetric} />
                    </CardAction>
                    <TabsList
                        variant="sliding"
                        className="col-span-full mt-1 max-w-full justify-start"
                        aria-label="Technology view"
                        data-test="campaign-insights-technology-tabs"
                    >
                        <TabsTrigger value="client">Client</TabsTrigger>
                        <TabsTrigger value="device">Device</TabsTrigger>
                        <TabsTrigger value="network">Network</TabsTrigger>
                    </TabsList>
                </CardHeader>
                <CardContent className="flex min-h-0 flex-1 flex-col">
                    <TabsContent
                        value="client"
                        className="flex min-h-0 flex-1 flex-col"
                    >
                        <InsightBarList
                            rows={insights.clients}
                            metric={metric}
                            kind="client"
                        />
                    </TabsContent>
                    <TabsContent
                        value="device"
                        className="flex min-h-0 flex-1 flex-col"
                    >
                        <InsightBarList
                            rows={insights.devices}
                            metric={metric}
                            kind="device"
                        />
                    </TabsContent>
                    <TabsContent
                        value="network"
                        className="flex min-h-0 flex-1 flex-col"
                    >
                        <InsightBarList
                            rows={insights.networks}
                            metric={metric}
                            kind="network"
                        />
                    </TabsContent>
                </CardContent>
            </Card>
        </Tabs>
    );
}

function PrivacyCard({ insights }: { insights: CampaignInsightsData }) {
    const retentionDays = insights.privacy.event_retention_days;

    return (
        <Card size="sm" data-test="campaign-privacy-card">
            <CardHeader className="border-b">
                <CardTitle>Privacy-safe tracking</CardTitle>
            </CardHeader>
            <CardContent>
                <div className="grid gap-3 sm:grid-cols-3">
                    <PrivacyFact
                        icon={Shield01Icon}
                        title="No raw IPs"
                        description="Addresses are discarded after geography is derived."
                        hint="The original IP is never written to the database. Only a keyed hash, user agent, and derived location stay on the event payload."
                    />
                    <PrivacyFact
                        icon={Clock01Icon}
                        title={
                            retentionDays > 0
                                ? `${retentionDays}-day retention`
                                : 'No auto-pruning'
                        }
                        description={
                            retentionDays > 0
                                ? 'Processed event payloads are removed after this window.'
                                : 'Automatic event-payload pruning is turned off.'
                        }
                        hint={
                            retentionDays > 0
                                ? `Processed event payloads are retained for ${retentionDays} days, then pruned. Aggregated campaign insights stay available.`
                                : 'Automatic event-payload pruning is disabled. Aggregated campaign insights remain available when events are removed.'
                        }
                    />
                    <PrivacyFact
                        icon={Globe02Icon}
                        title="Aggregates remain"
                        description="Human, location, and technology insights stay on the report."
                        hint="Campaign insight totals are stored as aggregates, so the report still works after event payloads are pruned."
                    />
                </div>
            </CardContent>
        </Card>
    );
}

function PrivacyFact({
    icon,
    title,
    description,
    hint,
}: {
    icon: IconSvgElement;
    title: string;
    description: string;
    hint: string;
}) {
    return (
        <div className="flex gap-3 rounded-lg bg-muted/50 p-3">
            <span className="flex size-9 shrink-0 items-center justify-center rounded-md bg-background ring-1 ring-foreground/10">
                <HugeiconsIcon
                    icon={icon}
                    className="size-4 text-muted-foreground"
                    aria-hidden="true"
                />
            </span>
            <div className="min-w-0 flex-1">
                <div className="flex items-center gap-1">
                    <p className="font-medium">{title}</p>
                    <Tooltip>
                        <TooltipTrigger
                            render={
                                <button
                                    type="button"
                                    className="rounded-sm text-muted-foreground transition-colors hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none"
                                    aria-label={hint}
                                />
                            }
                        >
                            <HugeiconsIcon
                                icon={InformationCircleIcon}
                                className="size-3.5"
                            />
                        </TooltipTrigger>
                        <TooltipContent>{hint}</TooltipContent>
                    </Tooltip>
                </div>
                <p className="text-xs text-muted-foreground">{description}</p>
            </div>
        </div>
    );
}

function MetricToggle({
    metric,
    onChange,
}: {
    metric: CampaignInsightMetric;
    onChange: (metric: CampaignInsightMetric) => void;
}) {
    return (
        <Tabs
            value={metric}
            onValueChange={(value) => {
                if (value === 'opens' || value === 'clicks') {
                    onChange(value);
                }
            }}
        >
            <TabsList variant="sliding" aria-label="Engagement metric">
                <TabsTrigger value="opens">Opens</TabsTrigger>
                <TabsTrigger value="clicks">Clicks</TabsTrigger>
            </TabsList>
        </Tabs>
    );
}

function InsightMetric({
    label,
    value,
    detail,
}: {
    label: string;
    value: string;
    detail: string;
}) {
    return (
        <div className="flex min-w-0 flex-col gap-1 px-4 py-2 first:pl-3 last:pr-3">
            <p className="truncate text-xs text-muted-foreground">{label}</p>
            <p className="text-2xl font-semibold tabular-nums">{value}</p>
            <p className="truncate text-xs text-muted-foreground">{detail}</p>
        </div>
    );
}

function InsightBarList({
    rows,
    metric,
    kind,
}: {
    rows: CampaignInsightRow[];
    metric: CampaignInsightMetric;
    kind?: InsightRowKind;
}) {
    const maximumValue = Math.max(
        ...rows.map((row) => insightMetricValue(row, metric)),
        0,
    );

    if (!rows.length) {
        return (
            <div className="flex min-h-52 flex-1 items-center justify-center text-sm text-muted-foreground">
                No human {metric} data yet.
            </div>
        );
    }

    return (
        <ol className="flex min-h-0 flex-1 flex-col gap-1 overflow-y-auto pr-1">
            {rows.map((row) => (
                <li
                    key={row.key}
                    className="relative overflow-hidden rounded-md"
                    title={row.label}
                >
                    <InsightBar
                        width={barWidth(
                            insightMetricValue(row, metric),
                            maximumValue,
                        )}
                    />
                    <div className="relative flex min-h-9 items-center gap-2 px-3 py-2">
                        <InsightRowMarker row={row} kind={kind} />
                        <span className="min-w-0 flex-1 truncate font-medium">
                            {row.label}
                        </span>
                        <span className="tabular-nums">
                            {insightMetricValue(row, metric).toLocaleString()}
                        </span>
                    </div>
                </li>
            ))}
        </ol>
    );
}

function InsightRowMarker({
    row,
    kind,
}: {
    row: CampaignInsightRow;
    kind?: InsightRowKind;
}) {
    if (kind === 'country' || kind === 'region' || kind === 'city') {
        return <CountryFlag code={insightCountryCode(row.key)} />;
    }

    if (kind === 'client') {
        return <ClientIcon label={row.label} />;
    }

    const icon =
        kind === 'device'
            ? deviceIcon(row.label)
            : kind === 'network'
              ? WifiLocationIcon
              : Globe02Icon;

    return (
        <HugeiconsIcon
            icon={icon}
            className="size-4 shrink-0 text-muted-foreground"
            aria-hidden="true"
        />
    );
}

/**
 * Country, region, and city keys are all prefixed with the ISO 3166-1 alpha-2
 * country code: `US`, `US:CA`, and `US:CA:san-francisco`.
 */
function insightCountryCode(key: string): string {
    return key.split(':')[0] ?? '';
}

function deviceIcon(label: string) {
    const normalizedLabel = label.toLowerCase();

    if (
        normalizedLabel.includes('mobile') ||
        normalizedLabel.includes('phone')
    ) {
        return SmartPhone01Icon;
    }

    if (normalizedLabel.includes('tablet')) {
        return Tablet01Icon;
    }

    if (normalizedLabel.includes('server')) {
        return ServerStack01Icon;
    }

    if (normalizedLabel.includes('unknown')) {
        return HelpCircleIcon;
    }

    return ComputerIcon;
}

function InsightBar({ width }: { width: number }) {
    return (
        <span
            aria-hidden="true"
            className="absolute inset-y-0 left-0 bg-primary/10 transition-[width] duration-300 ease-out motion-reduce:transition-none"
            style={{ width: `${width}%` }}
        />
    );
}

function insightMetricValue(
    row: Pick<CampaignInsightRow, 'unique_opens' | 'unique_clicks'>,
    metric: CampaignInsightMetric,
): number {
    return metric === 'opens' ? row.unique_opens : row.unique_clicks;
}

function barWidth(value: number, maximumValue: number): number {
    if (value === 0 || maximumValue === 0) {
        return 0;
    }

    return Math.max((value / maximumValue) * 100, 4);
}

function trafficVariant(
    classification: CampaignTrafficRow['classification'],
): 'success' | 'destructive' | 'secondary' | 'default' {
    return TRAFFIC_VARIANTS[classification];
}
