import {
    ArrowRight01Icon,
    MailAtSign02Icon,
    MailSend01Icon,
    NodeEditIcon,
    UserAddIcon,
    UserGroupIcon,
} from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import type { IconSvgElement } from '@hugeicons/react';
import { Head, Link, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { Area, AreaChart, CartesianGrid, XAxis, YAxis } from 'recharts';
import PendingInvitationsModal from '@/components/pending-invitations-modal';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardAction,
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
import {
    Empty,
    EmptyContent,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { Skeleton } from '@/components/ui/skeleton';
import { useMounted } from '@/hooks/use-mounted';
import {
    CAMPAIGN_STATUS_LABELS,
    campaignStatusVariant,
} from '@/lib/email-status';
import { formatRelativeTime } from '@/lib/format';
import { dashboard } from '@/routes';
import { index as audiencesIndex } from '@/routes/audiences';
import { index as automationsIndex } from '@/routes/automations';
import { index as campaignsIndex, show as showCampaign } from '@/routes/emails';
import type { DashboardInvitation, EmailCampaignStatus } from '@/types';

type DashboardData = {
    overview: {
        subscribers: number;
        newSubscribers: number;
        deliveryRate: number | null;
        activeAutomations: number;
    };
    subscriberGrowth: { date: string; subscribers: number }[];
    recentCampaigns: {
        uuid: string;
        name: string;
        status: EmailCampaignStatus;
        recipients: number;
        delivered: number;
        /** False when no recipient was sent through SES, which is the only transport that confirms delivery. */
        deliveryReported: boolean;
        sentAt: string | null;
    }[];
};

type Props = {
    pendingInvitations?: DashboardInvitation[];
    dashboard: DashboardData;
};

const CHART_CONFIG = {
    total: { label: 'Total new subscribers', color: 'var(--primary)' },
} satisfies ChartConfig;

export default function Dashboard({
    pendingInvitations = [],
    dashboard: dashboardData,
}: Props) {
    const { auth, currentTeam } = usePage().props;
    const [showInvitations, setShowInvitations] = useState(
        pendingInvitations.length > 0,
    );

    // Cumulative running total reads better than spiky per-day counts.
    const growth = useMemo(
        () =>
            dashboardData.subscriberGrowth.reduce<
                { date: string; total: number }[]
            >((points, point) => {
                const previous = points.at(-1)?.total ?? 0;

                return [
                    ...points,
                    { date: point.date, total: previous + point.subscribers },
                ];
            }, []),
        [dashboardData.subscriberGrowth],
    );

    if (!currentTeam) {
        return null;
    }

    return (
        <>
            <Head title="Dashboard" />
            <PendingInvitationsModal
                invitations={pendingInvitations}
                open={pendingInvitations.length > 0 && showInvitations}
                onOpenChange={setShowInvitations}
            />
            <div className="flex flex-1 flex-col gap-6">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                    <div className="flex flex-col gap-1">
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Good to see you, {firstName(auth.user.name)}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Here is how {currentTeam.name} is growing and
                            sending.
                        </p>
                    </div>
                    <Button
                        variant="outline"
                        nativeButton={false}
                        render={
                            <Link
                                href={campaignsIndex(currentTeam.slug)}
                                prefetch
                            />
                        }
                    >
                        View campaigns
                    </Button>
                </div>

                <div className="grid grid-cols-2 gap-4 xl:grid-cols-4">
                    <MetricCard
                        title="Subscribers"
                        value={dashboardData.overview.subscribers.toLocaleString()}
                        description="Active across all audiences"
                        icon={UserGroupIcon}
                    />
                    <MetricCard
                        title="New subscribers"
                        value={dashboardData.overview.newSubscribers.toLocaleString()}
                        description="Joined in the last 30 days"
                        icon={UserAddIcon}
                    />
                    <MetricCard
                        title="Delivery rate"
                        value={
                            dashboardData.overview.deliveryRate === null
                                ? '—'
                                : `${dashboardData.overview.deliveryRate}%`
                        }
                        description={
                            dashboardData.overview.deliveryRate === null
                                ? 'No Amazon SES deliveries in the last 30 days'
                                : 'Confirmed by Amazon SES in the last 30 days'
                        }
                        icon={MailSend01Icon}
                    />
                    <MetricCard
                        title="Active automations"
                        value={dashboardData.overview.activeAutomations.toLocaleString()}
                        description="Currently active"
                        icon={NodeEditIcon}
                    />
                </div>

                <div className="grid gap-6 xl:grid-cols-[minmax(0,1.4fr)_minmax(20rem,0.9fr)]">
                    <SubscriberGrowthChart data={growth} />
                    <QuickLinks teamSlug={currentTeam.slug} />
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Recent campaigns</CardTitle>
                        <CardDescription>
                            The latest campaigns sent by {currentTeam.name}.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {dashboardData.recentCampaigns.length === 0 ? (
                            <Empty className="border">
                                <EmptyHeader>
                                    <EmptyMedia variant="icon">
                                        <HugeiconsIcon
                                            icon={MailAtSign02Icon}
                                        />
                                    </EmptyMedia>
                                    <EmptyTitle>
                                        No campaigns sent yet
                                    </EmptyTitle>
                                    <EmptyDescription>
                                        Create your first campaign when you are
                                        ready to reach an audience.
                                    </EmptyDescription>
                                </EmptyHeader>
                                <EmptyContent>
                                    <Button
                                        nativeButton={false}
                                        render={
                                            <Link
                                                href={campaignsIndex(
                                                    currentTeam.slug,
                                                )}
                                                prefetch
                                            />
                                        }
                                    >
                                        Go to campaigns
                                    </Button>
                                </EmptyContent>
                            </Empty>
                        ) : (
                            <div className="flex flex-col divide-y">
                                {dashboardData.recentCampaigns.map(
                                    (campaign) => (
                                        <Link
                                            key={campaign.uuid}
                                            href={showCampaign([
                                                currentTeam.slug,
                                                campaign.uuid,
                                            ])}
                                            prefetch
                                            className="flex flex-col gap-3 py-4 first:pt-0 last:pb-0 sm:flex-row sm:items-center sm:justify-between"
                                        >
                                            <div className="flex min-w-0 flex-col gap-1">
                                                <span className="truncate font-medium">
                                                    {campaign.name}
                                                </span>
                                                <span className="text-sm text-muted-foreground">
                                                    {campaign.sentAt
                                                        ? `${formatRelativeTime(campaign.sentAt)} ago`
                                                        : 'Preparing delivery'}
                                                </span>
                                            </div>
                                            <div className="flex items-center gap-3 sm:justify-end">
                                                <span className="text-sm text-muted-foreground tabular-nums">
                                                    {campaign.deliveryReported ? (
                                                        <>
                                                            {campaign.delivered.toLocaleString()}{' '}
                                                            /{' '}
                                                            {campaign.recipients.toLocaleString()}{' '}
                                                            delivered
                                                        </>
                                                    ) : (
                                                        'Delivery not reported'
                                                    )}
                                                </span>
                                                <Badge
                                                    variant={campaignStatusVariant(
                                                        campaign.status,
                                                    )}
                                                >
                                                    {
                                                        CAMPAIGN_STATUS_LABELS[
                                                            campaign.status
                                                        ]
                                                    }
                                                </Badge>
                                            </div>
                                        </Link>
                                    ),
                                )}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

function firstName(fullName: string): string {
    return fullName.trim().split(/\s+/)[0] ?? fullName;
}

function MetricCard({
    title,
    value,
    description,
    icon,
}: {
    title: string;
    value: string;
    description: string;
    icon: IconSvgElement;
}) {
    return (
        <Card>
            <CardHeader>
                <CardDescription>{title}</CardDescription>
                <CardAction>
                    <div className="flex size-8 items-center justify-center rounded-lg bg-muted text-muted-foreground">
                        <HugeiconsIcon
                            icon={icon}
                            className="size-4"
                            aria-hidden
                        />
                    </div>
                </CardAction>
                <CardTitle className="text-2xl font-semibold">
                    {value}
                </CardTitle>
            </CardHeader>
            <CardContent>
                <p className="text-sm text-muted-foreground">{description}</p>
            </CardContent>
        </Card>
    );
}

function QuickLinks({ teamSlug }: { teamSlug: string }) {
    const links = [
        {
            href: audiencesIndex(teamSlug),
            icon: UserGroupIcon,
            title: 'Manage audiences',
            description: 'Lists, segments, and subscribers',
        },
        {
            href: campaignsIndex(teamSlug),
            icon: MailAtSign02Icon,
            title: 'Review campaigns',
            description: 'How recent sends performed',
        },
        {
            href: automationsIndex(teamSlug),
            icon: NodeEditIcon,
            title: 'Open automations',
            description: 'Build and tune workflows',
        },
    ];

    return (
        <Card className="flex flex-col">
            <CardHeader>
                <CardTitle>Keep things moving</CardTitle>
                <CardDescription>
                    Jump back into the areas your team uses most.
                </CardDescription>
            </CardHeader>
            <CardContent className="flex flex-1 flex-col justify-center">
                <nav aria-label="Quick links" className="flex flex-col gap-1">
                    {links.map((link) => (
                        <Link
                            key={link.title}
                            href={link.href}
                            prefetch
                            className="-mx-2 flex items-center gap-3 rounded-lg px-2 py-2 transition-colors hover:bg-muted/60"
                        >
                            <span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-muted text-muted-foreground">
                                <HugeiconsIcon
                                    icon={link.icon}
                                    className="size-4"
                                    aria-hidden
                                />
                            </span>
                            <span className="flex min-w-0 flex-col gap-0.5">
                                <span className="text-sm leading-none font-medium">
                                    {link.title}
                                </span>
                                <span className="truncate text-xs text-muted-foreground">
                                    {link.description}
                                </span>
                            </span>
                            <HugeiconsIcon
                                icon={ArrowRight01Icon}
                                className="ms-auto size-4 shrink-0 text-muted-foreground"
                                aria-hidden
                            />
                        </Link>
                    ))}
                </nav>
            </CardContent>
        </Card>
    );
}

function SubscriberGrowthChart({
    data,
}: {
    data: { date: string; total: number }[];
}) {
    const mounted = useMounted();

    return (
        <Card>
            <CardHeader>
                <CardTitle>Subscriber growth</CardTitle>
                <CardDescription>
                    Running total of new subscribers over the last 30 days.
                </CardDescription>
            </CardHeader>
            <CardContent>
                {mounted ? (
                    <ChartContainer
                        config={CHART_CONFIG}
                        className="aspect-auto h-72 w-full"
                    >
                        <AreaChart
                            accessibilityLayer
                            data={data}
                            margin={{ left: 12, right: 12 }}
                        >
                            <defs>
                                <linearGradient
                                    id="fillSubscriberGrowth"
                                    x1="0"
                                    y1="0"
                                    x2="0"
                                    y2="1"
                                >
                                    <stop
                                        offset="5%"
                                        stopColor="var(--color-total)"
                                        stopOpacity={0.25}
                                    />
                                    <stop
                                        offset="95%"
                                        stopColor="var(--color-total)"
                                        stopOpacity={0.02}
                                    />
                                </linearGradient>
                            </defs>
                            <CartesianGrid vertical={false} />
                            <XAxis
                                dataKey="date"
                                tickLine={false}
                                axisLine={false}
                                tickMargin={8}
                                minTickGap={32}
                                tickFormatter={formatChartDate}
                            />
                            <YAxis
                                width={36}
                                tickLine={false}
                                axisLine={false}
                                allowDecimals={false}
                            />
                            <ChartTooltip
                                content={
                                    <ChartTooltipContent
                                        labelFormatter={(value) =>
                                            formatChartTooltip(String(value))
                                        }
                                    />
                                }
                            />
                            <Area
                                dataKey="total"
                                type="monotone"
                                stroke="var(--color-total)"
                                strokeWidth={2}
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                fill="url(#fillSubscriberGrowth)"
                                dot={false}
                                activeDot={{
                                    r: 4,
                                    strokeWidth: 2,
                                    stroke: 'var(--card)',
                                }}
                                isAnimationActive={false}
                            />
                        </AreaChart>
                    </ChartContainer>
                ) : (
                    <Skeleton className="h-72 w-full" />
                )}
            </CardContent>
        </Card>
    );
}

function formatChartDate(value: string): string {
    return new Date(`${value}T00:00:00`).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
    });
}

function formatChartTooltip(value: string): string {
    return new Date(`${value}T00:00:00`).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

Dashboard.layout = (props: { currentTeam?: { slug: string } | null }) => ({
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: props.currentTeam ? dashboard(props.currentTeam.slug) : '/',
        },
    ],
});
