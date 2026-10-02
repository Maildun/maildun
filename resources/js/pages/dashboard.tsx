import {
    Add01Icon,
    Alert02Icon,
    ArrowDown01Icon,
    ArrowUpRight01Icon,
    Calendar01Icon,
    CheckmarkCircle02Icon,
    Edit03Icon,
    MailAtSign02Icon,
    MailOpen01Icon,
    MailSend01Icon,
    MouseLeftClick01Icon,
    UserAdd01Icon,
    UserGroupIcon,
} from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import type { IconSvgElement } from '@hugeicons/react';
import { Head, Link, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { Area, AreaChart, CartesianGrid, XAxis, YAxis } from 'recharts';
import { STEP_COPY } from '@/components/getting-started-checklist';
import { MetricCard } from '@/components/metric-card';
import PendingInvitationsModal from '@/components/pending-invitations-modal';
import {
    SlidingUnderlineList,
    slidingUnderlineInactiveClassName,
} from '@/components/sliding-underline-list';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Callout,
    CalloutContent,
    CalloutHeading,
    CalloutText,
} from '@/components/ui/callout';
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
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Empty,
    EmptyContent,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { Separator } from '@/components/ui/separator';
import { Skeleton } from '@/components/ui/skeleton';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useMounted } from '@/hooks/use-mounted';
import {
    CAMPAIGN_STATUS_LABELS,
    campaignStatusVariant,
} from '@/lib/email-status';
import { formatRelativeTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import {
    edit as editCampaign,
    index as campaignsIndex,
    show as showCampaign,
} from '@/routes/emails';
import type {
    DashboardInvitation,
    EmailCampaignStatus,
    OnboardingChecklist,
} from '@/types';

type DashboardData = {
    period: 7 | 30 | 90;
    overview: {
        subscribers: number;
        newSubscribers: number;
        deliveryRate: number | null;
        openRate: number | null;
        clickRate: number | null;
    };
    performance: {
        date: string;
        subscribers: number;
        sent: number;
        opens: number;
        clicks: number;
    }[];
    deliveryIssues: {
        failed: number;
        bounced: number;
        complained: number;
    };
    draftCampaigns: {
        uuid: string;
        name: string;
        updatedAt: string | null;
    }[];
    recentCampaigns: {
        uuid: string;
        name: string;
        status: EmailCampaignStatus;
        recipients: number;
        delivered: number;
        /** False when no recipient was sent through SES, which is the only transport that confirms delivery. */
        deliveryReported: boolean;
        opened: number;
        clicked: number;
        deliveryRate: number | null;
        openRate: number | null;
        clickRate: number | null;
        sentAt: string | null;
    }[];
};

type Props = {
    pendingInvitations?: DashboardInvitation[];
    canManageCampaigns: boolean;
    dashboard: DashboardData;
};

type PerformanceMetric = 'subscribers' | 'sent' | 'opens' | 'clicks';

const PERIODS = [7, 30, 90] as const;

const PERFORMANCE_METRICS: {
    key: PerformanceMetric;
    label: string;
    description: string;
    emptyTitle: string;
    icon: IconSvgElement;
}[] = [
    {
        key: 'subscribers',
        label: 'Subscribers',
        description: 'Running total of new subscribers',
        emptyTitle: 'No new subscribers yet',
        icon: UserAdd01Icon,
    },
    {
        key: 'sent',
        label: 'Emails sent',
        description: 'Campaign emails sent each day',
        emptyTitle: 'No emails sent in this period',
        icon: MailSend01Icon,
    },
    {
        key: 'opens',
        label: 'Opens',
        description: 'Unique opens recorded each day',
        emptyTitle: 'No opens recorded in this period',
        icon: MailOpen01Icon,
    },
    {
        key: 'clicks',
        label: 'Clicks',
        description: 'Unique clicks recorded each day',
        emptyTitle: 'No clicks recorded in this period',
        icon: MouseLeftClick01Icon,
    },
];

export default function Dashboard({
    pendingInvitations = [],
    canManageCampaigns,
    dashboard: dashboardData,
}: Props) {
    const { auth, currentTeam, onboarding } = usePage().props;
    const [showInvitations, setShowInvitations] = useState(
        pendingInvitations.length > 0,
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
                        <h1 className="font-heading text-2xl font-semibold tracking-tight">
                            Good to see you, {firstName(auth.user.name)}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Here is how {currentTeam.name} is growing and
                            sending.
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <PeriodMenu
                            teamSlug={currentTeam.slug}
                            period={dashboardData.period}
                        />
                        <Button
                            variant={canManageCampaigns ? 'default' : 'outline'}
                            nativeButton={false}
                            render={
                                <Link
                                    href={campaignsIndex(
                                        currentTeam.slug,
                                        canManageCampaigns
                                            ? { query: { compose: 1 } }
                                            : undefined,
                                    )}
                                    prefetch
                                />
                            }
                        >
                            {canManageCampaigns && (
                                <HugeiconsIcon
                                    icon={Add01Icon}
                                    data-icon="inline-start"
                                />
                            )}
                            {canManageCampaigns
                                ? 'Create campaign'
                                : 'View campaigns'}
                        </Button>
                    </div>
                </div>

                <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-5">
                    <MetricCard
                        label="Subscribers"
                        value={dashboardData.overview.subscribers.toLocaleString()}
                        detail="Active across all audiences"
                        icon={UserGroupIcon}
                        iconClassName="bg-violet-500/10 text-violet-600 dark:text-violet-400"
                    />
                    <MetricCard
                        label="New subscribers"
                        value={dashboardData.overview.newSubscribers.toLocaleString()}
                        detail={`Joined in the last ${dashboardData.period} days`}
                        icon={UserAdd01Icon}
                        iconClassName="bg-blue-500/10 text-blue-600 dark:text-blue-400"
                    />
                    <MetricCard
                        label="Delivery rate"
                        value={
                            dashboardData.overview.deliveryRate === null
                                ? '—'
                                : `${dashboardData.overview.deliveryRate}%`
                        }
                        detail={
                            dashboardData.overview.deliveryRate === null
                                ? 'No Amazon SES deliveries in this period'
                                : `Confirmed by Amazon SES in the last ${dashboardData.period} days`
                        }
                        icon={MailSend01Icon}
                        iconClassName="bg-emerald-500/10 text-emerald-600 dark:text-emerald-400"
                    />
                    <MetricCard
                        label="Open rate"
                        value={formatRate(dashboardData.overview.openRate)}
                        detail={`Last ${dashboardData.period} days`}
                        icon={MailOpen01Icon}
                        iconClassName="bg-amber-500/10 text-amber-600 dark:text-amber-400"
                    />
                    <MetricCard
                        label="Click rate"
                        value={formatRate(dashboardData.overview.clickRate)}
                        detail={`Last ${dashboardData.period} days`}
                        icon={MouseLeftClick01Icon}
                        iconClassName="bg-rose-500/10 text-rose-600 dark:text-rose-400"
                    />
                </div>

                <div className="grid items-start gap-6 xl:grid-cols-[minmax(0,1.6fr)_minmax(19rem,0.7fr)]">
                    <PerformanceChart
                        data={dashboardData.performance}
                        period={dashboardData.period}
                    />
                    <DashboardSidebar
                        teamSlug={currentTeam.slug}
                        drafts={dashboardData.draftCampaigns}
                        issues={dashboardData.deliveryIssues}
                        onboarding={onboarding}
                    />
                </div>

                <RecentCampaigns
                    campaigns={dashboardData.recentCampaigns}
                    teamName={currentTeam.name}
                    teamSlug={currentTeam.slug}
                    canManage={canManageCampaigns}
                />
            </div>
        </>
    );
}

function firstName(fullName: string): string {
    return fullName.trim().split(/\s+/)[0] ?? fullName;
}

function PeriodMenu({
    teamSlug,
    period,
}: {
    teamSlug: string;
    period: number;
}) {
    return (
        <DropdownMenu>
            <DropdownMenuTrigger
                render={<Button variant="outline" aria-label="Date range" />}
            >
                <HugeiconsIcon icon={Calendar01Icon} data-icon="inline-start" />
                Last {period} days
                <HugeiconsIcon icon={ArrowDown01Icon} data-icon="inline-end" />
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                <DropdownMenuGroup>
                    {PERIODS.map((option) => (
                        <DropdownMenuItem
                            key={option}
                            render={
                                <Link
                                    href={dashboard(teamSlug, {
                                        query: { period: option },
                                    })}
                                    preserveScroll
                                />
                            }
                            className={cn(option === period && 'font-medium')}
                        >
                            Last {option} days
                            {option === period && (
                                <span className="ms-auto size-1.5 rounded-full bg-primary" />
                            )}
                        </DropdownMenuItem>
                    ))}
                </DropdownMenuGroup>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

function DashboardSidebar({
    teamSlug,
    drafts,
    issues,
    onboarding,
}: {
    teamSlug: string;
    drafts: DashboardData['draftCampaigns'];
    issues: DashboardData['deliveryIssues'];
    onboarding: OnboardingChecklist | null;
}) {
    const issueCount = issues.failed + issues.bounced + issues.complained;
    const setupStep = onboarding?.steps.find(
        (step) =>
            !step.completed &&
            (step.key === 'delivery' || step.key === 'sender'),
    );
    const setupCopy = setupStep ? STEP_COPY[setupStep.key] : null;

    return (
        <Card data-test="dashboard-activity-center">
            <CardHeader>
                <CardTitle>Activity center</CardTitle>
                <CardDescription>
                    Draft work and sending health at a glance.
                </CardDescription>
            </CardHeader>
            <CardContent className="flex flex-col gap-5">
                {issueCount > 0 ? (
                    <Callout variant="danger" icon={Alert02Icon} role="alert">
                        <CalloutContent>
                            <CalloutHeading>
                                Delivery needs attention
                            </CalloutHeading>
                            <CalloutText>
                                {formatIssueSummary(issues)} in this period.{' '}
                                <Link href={campaignsIndex(teamSlug)}>
                                    Review campaigns
                                </Link>
                            </CalloutText>
                        </CalloutContent>
                    </Callout>
                ) : setupCopy ? (
                    <Callout icon={Alert02Icon}>
                        <CalloutContent>
                            <CalloutHeading>{setupCopy.title}</CalloutHeading>
                            <CalloutText>
                                {setupCopy.description}{' '}
                                <Link
                                    href={setupCopy.href(teamSlug)}
                                    className="font-medium"
                                >
                                    {setupCopy.action}
                                </Link>
                            </CalloutText>
                        </CalloutContent>
                    </Callout>
                ) : (
                    <Callout variant="success" icon={CheckmarkCircle02Icon}>
                        <CalloutContent>
                            <CalloutHeading>
                                Delivery looks healthy
                            </CalloutHeading>
                            <CalloutText>
                                No sending issues in this period.
                            </CalloutText>
                        </CalloutContent>
                    </Callout>
                )}

                <Separator />
                <div className="flex flex-col gap-3">
                    <div className="flex items-center justify-between gap-3">
                        <p className="text-sm font-medium">Recent drafts</p>
                        <Button
                            variant="ghost"
                            size="sm"
                            nativeButton={false}
                            render={
                                <Link
                                    href={campaignsIndex(teamSlug, {
                                        query: { status: 'draft' },
                                    })}
                                    prefetch
                                />
                            }
                        >
                            View all
                        </Button>
                    </div>
                    {drafts.length === 0 ? (
                        <Empty className="px-3 py-5">
                            <EmptyHeader>
                                <EmptyMedia variant="icon">
                                    <HugeiconsIcon icon={Edit03Icon} />
                                </EmptyMedia>
                                <EmptyTitle>
                                    No campaigns in progress
                                </EmptyTitle>
                                <EmptyDescription>
                                    New campaign drafts will appear here.
                                </EmptyDescription>
                            </EmptyHeader>
                        </Empty>
                    ) : (
                        <div className="flex flex-col divide-y">
                            {drafts.map((draft) => (
                                <Link
                                    key={draft.uuid}
                                    href={editCampaign([teamSlug, draft.uuid])}
                                    prefetch
                                    className="-mx-2 flex min-w-0 items-center gap-3 rounded-lg px-2 py-3 transition-colors hover:bg-muted/60 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                >
                                    <span className="grid size-9 shrink-0 place-items-center rounded-lg bg-muted text-muted-foreground">
                                        <HugeiconsIcon
                                            icon={Edit03Icon}
                                            className="size-4"
                                        />
                                    </span>
                                    <span className="flex min-w-0 flex-1 flex-col gap-1">
                                        <span className="truncate text-sm font-medium">
                                            {draft.name}
                                        </span>
                                        <span className="text-xs text-muted-foreground">
                                            {draft.updatedAt
                                                ? formatActivityTime(
                                                      draft.updatedAt,
                                                      'Updated',
                                                  )
                                                : 'Draft'}
                                        </span>
                                    </span>
                                    <HugeiconsIcon
                                        icon={ArrowUpRight01Icon}
                                        className="size-4 shrink-0 text-muted-foreground"
                                    />
                                </Link>
                            ))}
                        </div>
                    )}
                </div>
            </CardContent>
        </Card>
    );
}

function PerformanceChart({
    data,
    period,
}: {
    data: DashboardData['performance'];
    period: number;
}) {
    const mounted = useMounted();
    const [activeMetric, setActiveMetric] =
        useState<PerformanceMetric>('subscribers');
    const metric = PERFORMANCE_METRICS.find(
        (option) => option.key === activeMetric,
    );
    const chartData = useMemo(
        () =>
            data.reduce<{ date: string; value: number }[]>(
                (points, point) => [
                    ...points,
                    {
                        date: point.date,
                        value:
                            activeMetric === 'subscribers'
                                ? (points.at(-1)?.value ?? 0) +
                                  point.subscribers
                                : point[activeMetric],
                    },
                ],
                [],
            ),
        [activeMetric, data],
    );
    const chartConfig = {
        value: {
            label: metric?.label ?? 'Performance',
            color: 'var(--primary)',
        },
    } satisfies ChartConfig;
    const hasActivity = chartData.some((point) => point.value > 0);

    return (
        <Card className="overflow-hidden" data-test="dashboard-performance">
            <CardHeader>
                <CardTitle>Performance</CardTitle>
                <CardDescription>
                    {metric?.description} over the last {period} days.
                </CardDescription>
            </CardHeader>
            <CardContent className="overflow-hidden p-0">
                <SlidingUnderlineList
                    activeKey={activeMetric}
                    className="sm:overflow-x-hidden"
                    aria-label="Performance metric"
                >
                    {PERFORMANCE_METRICS.map((option) => {
                        const active = option.key === activeMetric;

                        return (
                            <button
                                key={option.key}
                                type="button"
                                data-active={active}
                                aria-pressed={active}
                                onClick={() => setActiveMetric(option.key)}
                                className={cn(
                                    'relative flex min-w-28 flex-1 items-center justify-center gap-2 px-4 py-3.5 text-sm font-medium transition-colors duration-300 ease-out focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none motion-reduce:transition-none',
                                    !active &&
                                        slidingUnderlineInactiveClassName,
                                    !active && 'text-muted-foreground',
                                )}
                            >
                                <HugeiconsIcon
                                    icon={option.icon}
                                    className="size-4 shrink-0"
                                />
                                {option.label}
                            </button>
                        );
                    })}
                </SlidingUnderlineList>
                <div className="px-(--card-spacing) py-5">
                    {mounted && hasActivity ? (
                        <ChartContainer
                            config={chartConfig}
                            className="aspect-auto h-64 w-full sm:h-72"
                        >
                            <AreaChart
                                accessibilityLayer
                                data={chartData}
                                margin={{ left: 12, right: 12 }}
                            >
                                <defs>
                                    <linearGradient
                                        id="fillDashboardPerformance"
                                        x1="0"
                                        y1="0"
                                        x2="0"
                                        y2="1"
                                    >
                                        <stop
                                            offset="5%"
                                            stopColor="var(--color-value)"
                                            stopOpacity={0.25}
                                        />
                                        <stop
                                            offset="95%"
                                            stopColor="var(--color-value)"
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
                                                formatChartTooltip(
                                                    String(value),
                                                )
                                            }
                                        />
                                    }
                                />
                                <Area
                                    dataKey="value"
                                    type="monotone"
                                    stroke="var(--color-value)"
                                    strokeWidth={2}
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    fill="url(#fillDashboardPerformance)"
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
                    ) : mounted ? (
                        <Empty className="h-64 sm:h-72">
                            <EmptyHeader>
                                <EmptyMedia variant="icon">
                                    <HugeiconsIcon
                                        icon={metric?.icon ?? MailAtSign02Icon}
                                    />
                                </EmptyMedia>
                                <EmptyTitle>
                                    {metric?.emptyTitle ?? 'No activity yet'}
                                </EmptyTitle>
                                <EmptyDescription>
                                    Activity for this workspace will appear
                                    here.
                                </EmptyDescription>
                            </EmptyHeader>
                        </Empty>
                    ) : (
                        <Skeleton className="h-64 w-full sm:h-72" />
                    )}
                </div>
            </CardContent>
        </Card>
    );
}

function RecentCampaigns({
    campaigns,
    teamName,
    teamSlug,
    canManage,
}: {
    campaigns: DashboardData['recentCampaigns'];
    teamName: string;
    teamSlug: string;
    canManage: boolean;
}) {
    return (
        <Card data-test="dashboard-recent-campaigns">
            <CardHeader>
                <CardTitle>Recent campaigns</CardTitle>
                <CardDescription>
                    The latest campaign performance from {teamName}.
                </CardDescription>
                <CardAction>
                    <Button
                        variant="ghost"
                        size="sm"
                        nativeButton={false}
                        render={
                            <Link href={campaignsIndex(teamSlug)} prefetch />
                        }
                    >
                        View all
                    </Button>
                </CardAction>
            </CardHeader>
            <CardContent>
                {campaigns.length === 0 ? (
                    <Empty>
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <HugeiconsIcon icon={MailAtSign02Icon} />
                            </EmptyMedia>
                            <EmptyTitle>No campaigns sent yet</EmptyTitle>
                            <EmptyDescription>
                                Your sent campaigns and their performance will
                                appear here.
                            </EmptyDescription>
                        </EmptyHeader>
                        <EmptyContent>
                            <Button
                                nativeButton={false}
                                render={
                                    <Link
                                        href={campaignsIndex(
                                            teamSlug,
                                            canManage
                                                ? { query: { compose: 1 } }
                                                : undefined,
                                        )}
                                        prefetch
                                    />
                                }
                            >
                                {canManage
                                    ? 'Create campaign'
                                    : 'View campaigns'}
                            </Button>
                        </EmptyContent>
                    </Empty>
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Campaign</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead className="hidden md:table-cell">
                                    Sent
                                </TableHead>
                                <TableHead className="text-right">
                                    Recipients
                                </TableHead>
                                <TableHead className="text-right">
                                    Delivery
                                </TableHead>
                                <TableHead className="text-right">
                                    Opens
                                </TableHead>
                                <TableHead className="text-right">
                                    Clicks
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {campaigns.map((campaign) => (
                                <TableRow key={campaign.uuid}>
                                    <TableCell className="max-w-64">
                                        <Link
                                            href={showCampaign([
                                                teamSlug,
                                                campaign.uuid,
                                            ])}
                                            prefetch
                                            className="block truncate font-medium underline-offset-4 hover:underline"
                                        >
                                            {campaign.name}
                                        </Link>
                                    </TableCell>
                                    <TableCell>
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
                                    </TableCell>
                                    <TableCell className="hidden text-muted-foreground md:table-cell">
                                        {campaign.sentAt
                                            ? formatActivityTime(
                                                  campaign.sentAt,
                                                  '',
                                              )
                                            : '—'}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {campaign.recipients.toLocaleString()}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {formatRate(campaign.deliveryRate)}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {formatRate(campaign.openRate)}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {formatRate(campaign.clickRate)}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
            </CardContent>
        </Card>
    );
}

function formatRate(value: number | null): string {
    return value === null ? '—' : `${value}%`;
}

function formatIssueSummary(issues: DashboardData['deliveryIssues']): string {
    const parts = [
        issues.failed > 0 ? `${issues.failed.toLocaleString()} failed` : null,
        issues.bounced > 0
            ? `${issues.bounced.toLocaleString()} bounced`
            : null,
        issues.complained > 0
            ? `${issues.complained.toLocaleString()} complained`
            : null,
    ].filter((part): part is string => part !== null);

    return parts.join(', ');
}

function formatActivityTime(value: string, prefix: string): string {
    const relative = formatRelativeTime(value);
    const label =
        relative === 'Now'
            ? 'just now'
            : /^\d+[mhd]$/.test(relative)
              ? `${relative} ago`
              : relative;

    return prefix ? `${prefix} ${label}` : label;
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
