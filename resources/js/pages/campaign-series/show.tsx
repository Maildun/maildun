import {
    Add01Icon,
    Alert02Icon,
    ArrowLeft02Icon,
    ArrowUpRight01Icon,
    Cancel01Icon,
    Clock01Icon,
    Delete02Icon,
    Edit03Icon,
    FolderRemoveIcon,
    FoldersIcon,
    MailOpen01Icon,
    MailRemove01Icon,
    MailSend01Icon,
    MoreHorizontalIcon,
    MouseLeftClick01Icon,
    PieChartIcon,
    Target02Icon,
    UserGroupIcon,
} from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import type { FormEvent } from 'react';
import { CampaignSeriesDialog } from '@/components/campaign-series-dialog';
import EmailTemplatePicker from '@/components/email-template-picker';
import { HealthStat } from '@/components/health-stat';
import { MetricCard } from '@/components/metric-card';
import { MetricGauge } from '@/components/metric-gauge';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
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
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuSeparator,
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
import {
    Field,
    FieldContent,
    FieldDescription,
    FieldError,
    FieldGroup,
    FieldLegend,
    FieldSet,
    FieldTitle,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Progress, ProgressLabel } from '@/components/ui/progress';
import { Spinner } from '@/components/ui/spinner';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    CAMPAIGN_STATUS_LABELS,
    campaignStatusVariant,
} from '@/lib/email-status';
import { formatRelativeTime } from '@/lib/format';
import { destroy as destroySeries, index } from '@/routes/campaign_series';
import {
    destroy as removeCampaign,
    store as addCampaigns,
} from '@/routes/campaign_series/campaigns';
import { edit as editCampaign, show as showCampaign } from '@/routes/emails';
import type {
    AvailableSeriesCampaign,
    CampaignSeriesCampaign,
    CampaignSeriesShowProps,
} from '@/types';

export default function CampaignSeriesShow({
    series,
    report,
    availableCampaigns,
    templates,
    defaultEditor,
    goals,
    canManage,
}: CampaignSeriesShowProps) {
    const { currentTeam } = usePage().props;
    const [editOpen, setEditOpen] = useState(false);
    const [addOpen, setAddOpen] = useState(false);
    const [composeOpen, setComposeOpen] = useState(false);
    const [deleteOpen, setDeleteOpen] = useState(false);
    const [deleting, setDeleting] = useState(false);
    const { summary } = report;
    const sentCampaigns = report.campaigns.filter(
        (campaign) => campaign.status !== 'draft',
    );
    const bestClickRate = Math.max(
        0,
        ...sentCampaigns.map((campaign) => campaign.click_rate),
    );
    const queuedSends = sentCampaigns.reduce(
        (total, campaign) => total + campaign.recipient_count,
        0,
    );
    const progress =
        queuedSends > 0
            ? Math.round((summary.processed / queuedSends) * 100)
            : 0;
    const feedbackReported = summary.delivery_feedback !== 'unavailable';
    const lastSentAt = sentCampaigns
        .map((campaign) => campaign.sent_at)
        .filter((sentAt): sentAt is string => sentAt !== null)
        .sort()
        .at(-1);

    if (!currentTeam) {
        return null;
    }

    const deleteCampaignSeries = () => {
        router.delete(destroySeries.url([currentTeam.slug, series.uuid]), {
            onStart: () => setDeleting(true),
            onFinish: () => setDeleting(false),
        });
    };

    return (
        <>
            <Head title={series.name} />
            <div className="flex flex-1 flex-col gap-6">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                    <div className="flex min-w-0 flex-col gap-2">
                        <Button
                            size="sm"
                            variant="ghost"
                            className="-ml-2 self-start"
                            nativeButton={false}
                            render={<Link href={index(currentTeam.slug)} />}
                        >
                            <HugeiconsIcon
                                icon={ArrowLeft02Icon}
                                data-icon="inline-start"
                            />
                            Campaign series
                        </Button>
                        <div className="flex flex-wrap items-center gap-2">
                            <h1 className="text-2xl font-semibold tracking-tight">
                                {series.name}
                            </h1>
                            <Badge variant="secondary">
                                <HugeiconsIcon icon={Target02Icon} />
                                {series.goal_label}
                            </Badge>
                        </div>
                        {(series.objective || series.description) && (
                            <p className="max-w-3xl text-sm text-muted-foreground">
                                {[series.objective, series.description]
                                    .filter(Boolean)
                                    .join(' · ')}
                            </p>
                        )}
                    </div>

                    <div className="flex flex-col items-start gap-3 sm:items-end">
                        <div className="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-muted-foreground">
                            <span>
                                {lastSentAt
                                    ? `Last sent ${formatRelativeTime(lastSentAt)} ago`
                                    : 'Nothing sent yet'}
                            </span>
                            {series.primary_cta_url && (
                                <a
                                    href={series.primary_cta_url}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="group inline-flex items-center gap-1 underline-offset-4 hover:text-foreground hover:underline"
                                >
                                    Primary CTA
                                    <HugeiconsIcon
                                        icon={ArrowUpRight01Icon}
                                        className="size-3.5 transition-transform motion-safe:group-hover:translate-x-0.5 motion-safe:group-hover:-translate-y-0.5"
                                    />
                                </a>
                            )}
                        </div>
                        {canManage && (
                            <div className="flex flex-wrap gap-2">
                                <Button
                                    variant="outline"
                                    onClick={() => setAddOpen(true)}
                                >
                                    <HugeiconsIcon
                                        icon={FoldersIcon}
                                        data-icon="inline-start"
                                    />
                                    Add campaigns
                                </Button>
                                <Button onClick={() => setComposeOpen(true)}>
                                    <HugeiconsIcon
                                        icon={Add01Icon}
                                        data-icon="inline-start"
                                    />
                                    New campaign
                                </Button>
                                <DropdownMenu>
                                    <DropdownMenuTrigger
                                        render={
                                            <Button
                                                size="icon"
                                                variant="outline"
                                                aria-label="Series actions"
                                            />
                                        }
                                    >
                                        <HugeiconsIcon
                                            icon={MoreHorizontalIcon}
                                        />
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end">
                                        <DropdownMenuGroup>
                                            <DropdownMenuItem
                                                onClick={() =>
                                                    setEditOpen(true)
                                                }
                                            >
                                                <HugeiconsIcon
                                                    icon={Edit03Icon}
                                                />
                                                Edit series
                                            </DropdownMenuItem>
                                            <DropdownMenuSeparator />
                                            <DropdownMenuItem
                                                variant="destructive"
                                                onClick={() =>
                                                    setDeleteOpen(true)
                                                }
                                            >
                                                <HugeiconsIcon
                                                    icon={Delete02Icon}
                                                />
                                                Delete series
                                            </DropdownMenuItem>
                                        </DropdownMenuGroup>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </div>
                        )}
                    </div>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <MetricCard
                        label="Unique reach"
                        value={summary.unique_recipients.toLocaleString()}
                        detail={`${summary.sent_campaigns} of ${summary.campaigns} campaigns sent`}
                        hint="People who received at least one campaign in this series. Someone sent two campaigns counts once."
                        icon={UserGroupIcon}
                        iconClassName="bg-violet-500/10 text-violet-600 dark:text-violet-400"
                    />
                    <MetricCard
                        label="Unique opens"
                        value={`${summary.open_rate}%`}
                        detail={`${summary.opened.toLocaleString()} people opened`}
                        hint="Share of reached people who opened any campaign in this series at least once, including privacy proxies that load images automatically."
                        icon={MailOpen01Icon}
                        iconClassName="bg-amber-500/10 text-amber-600 dark:text-amber-400"
                    />
                    <MetricCard
                        label="Unique clicks"
                        value={`${summary.click_rate}%`}
                        detail={`${summary.clicked.toLocaleString()} people · ${summary.click_to_open_rate}% click-to-open`}
                        hint="Share of reached people who clicked any tracked link in this series. Click-to-open counts clickers among people who opened."
                        icon={MouseLeftClick01Icon}
                        iconClassName="bg-rose-500/10 text-rose-600 dark:text-rose-400"
                    />
                    {series.primary_cta_url ? (
                        <MetricCard
                            label="Primary CTA clicks"
                            value={summary.cta_clicks.toLocaleString()}
                            detail="Tracked clicks on the exact CTA URL"
                            hint="Every tracked click on the series' primary CTA URL across all of its campaigns, including repeat clicks."
                            icon={Target02Icon}
                            iconClassName="bg-emerald-500/10 text-emerald-600 dark:text-emerald-400"
                        />
                    ) : (
                        <MetricCard
                            label="Delivered"
                            value={
                                summary.delivery_rate === null
                                    ? '—'
                                    : `${summary.delivery_rate}%`
                            }
                            detail="Add a primary CTA to track the sales action"
                            hint="Share of Amazon SES and Maildun Send sends whose mail server confirmed receipt. SMTP does not report delivery."
                            icon={MailSend01Icon}
                            iconClassName="bg-emerald-500/10 text-emerald-600 dark:text-emerald-400"
                        />
                    )}
                </div>

                {report.has_mixed_recipients && (
                    <Callout icon={UserGroupIcon}>
                        <CalloutContent>
                            <CalloutHeading>
                                Different recipient groups
                            </CalloutHeading>
                            <CalloutText>
                                These campaigns use different audiences or
                                segments. Compare rates as directional signals
                                because the recipient mix can affect
                                performance.
                            </CalloutText>
                        </CalloutContent>
                    </Callout>
                )}

                <Card size="sm">
                    <CardHeader>
                        <CardTitle>Delivery health</CardTitle>
                        <CardDescription>
                            Sending progress and confirmed delivery across every
                            campaign in this series.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid items-center gap-6 p-5 sm:p-6 lg:grid-cols-[14rem_minmax(0,1fr)] lg:gap-8">
                        <MetricGauge
                            value={summary.delivery_rate}
                            label="Confirmed delivery"
                            detail={
                                summary.delivery_rate === null
                                    ? 'Delivery confirmation unavailable'
                                    : `${summary.delivered.toLocaleString()} of ${summary.feedback_recipient_count.toLocaleString()} confirmed`
                            }
                            className="justify-self-center"
                        />
                        <div className="flex min-w-0 flex-col gap-6">
                            <Progress
                                value={progress}
                                className="gap-3 [&_[data-slot=progress-indicator]]:rounded-full [&_[data-slot=progress-indicator]]:transition-[width] [&_[data-slot=progress-indicator]]:motion-reduce:transition-none [&_[data-slot=progress-track]]:h-2.5"
                            >
                                <ProgressLabel>Sends processed</ProgressLabel>
                                <span className="ml-auto text-sm text-muted-foreground tabular-nums">
                                    {summary.processed.toLocaleString()} of{' '}
                                    {queuedSends.toLocaleString()}
                                </span>
                            </Progress>
                            <div className="grid grid-cols-2 gap-x-4 gap-y-5 sm:grid-cols-4">
                                <HealthStat
                                    label="Failed"
                                    value={summary.failed}
                                    icon={Cancel01Icon}
                                    iconClassName="bg-rose-500/10 text-rose-600 dark:text-rose-400"
                                    tone="danger"
                                />
                                <HealthStat
                                    label="Bounced"
                                    value={
                                        feedbackReported
                                            ? summary.bounced
                                            : null
                                    }
                                    icon={MailRemove01Icon}
                                    iconClassName="bg-orange-500/10 text-orange-600 dark:text-orange-400"
                                    tone="danger"
                                    unavailableLabel="Not reported"
                                />
                                <HealthStat
                                    label="Complaints"
                                    value={
                                        feedbackReported
                                            ? summary.complained
                                            : null
                                    }
                                    icon={Alert02Icon}
                                    iconClassName="bg-amber-500/10 text-amber-600 dark:text-amber-400"
                                    tone="danger"
                                    unavailableLabel="Not reported"
                                />
                                <HealthStat
                                    label="Pending"
                                    value={Math.max(
                                        queuedSends - summary.processed,
                                        0,
                                    )}
                                    icon={Clock01Icon}
                                    iconClassName="bg-blue-500/10 text-blue-600 dark:text-blue-400"
                                />
                            </div>
                            <p className="text-xs/relaxed text-muted-foreground">
                                Retry failed sends from each campaign&apos;s
                                report. Permanent bounces and complaints are
                                left unsubscribed.
                                {summary.delivery_feedback === 'partial' &&
                                    ' Delivery confirmation only covers sends through Amazon SES or Maildun Send.'}
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <section className="flex flex-col gap-4">
                    <div className="flex flex-col gap-1">
                        <h2 className="text-base font-semibold">Campaigns</h2>
                        <p className="text-sm text-muted-foreground">
                            Each campaign&apos;s own rates, side by side. The
                            totals above count people who received more than one
                            campaign once.
                        </p>
                    </div>

                    {report.campaigns.length === 0 ? (
                        <Empty className="border">
                            <EmptyHeader>
                                <EmptyMedia variant="icon">
                                    <HugeiconsIcon icon={FoldersIcon} />
                                </EmptyMedia>
                                <EmptyTitle>
                                    No campaigns in this series
                                </EmptyTitle>
                                <EmptyDescription>
                                    Add an existing campaign or compose a new
                                    one for this sales goal.
                                </EmptyDescription>
                            </EmptyHeader>
                            {canManage && (
                                <EmptyContent>
                                    <div className="flex flex-wrap justify-center gap-2">
                                        <Button
                                            variant="outline"
                                            onClick={() => setAddOpen(true)}
                                        >
                                            Add existing
                                        </Button>
                                        <Button
                                            onClick={() => setComposeOpen(true)}
                                        >
                                            Compose campaign
                                        </Button>
                                    </div>
                                </EmptyContent>
                            )}
                        </Empty>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Campaign</TableHead>
                                    <TableHead>Recipients</TableHead>
                                    <TableHead className="text-right">
                                        Delivered
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Open rate
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Click rate
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Click-to-open
                                    </TableHead>
                                    {series.primary_cta_url && (
                                        <TableHead className="text-right">
                                            CTA clicks
                                        </TableHead>
                                    )}
                                    <TableHead>Sent</TableHead>
                                    <TableHead className="text-right">
                                        Actions
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {report.campaigns.map((campaign) => {
                                    const isDraft = campaign.status === 'draft';
                                    const isBest =
                                        bestClickRate > 0 &&
                                        !isDraft &&
                                        campaign.click_rate === bestClickRate;

                                    return (
                                        <TableRow
                                            key={campaign.uuid}
                                            data-test="series-campaign-row"
                                        >
                                            <TableCell>
                                                <div className="flex min-w-56 flex-col gap-1">
                                                    <div className="flex flex-wrap items-center gap-2">
                                                        <Link
                                                            href={campaignRoute(
                                                                currentTeam.slug,
                                                                campaign,
                                                            )}
                                                            prefetch
                                                            className="font-medium underline-offset-4 hover:underline"
                                                        >
                                                            {campaign.name}
                                                        </Link>
                                                        <Badge
                                                            variant={campaignStatusVariant(
                                                                campaign.status,
                                                            )}
                                                        >
                                                            {
                                                                CAMPAIGN_STATUS_LABELS[
                                                                    campaign
                                                                        .status
                                                                ]
                                                            }
                                                        </Badge>
                                                    </div>
                                                    <span className="max-w-sm truncate text-muted-foreground">
                                                        {campaign.subject}
                                                    </span>
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex min-w-40 flex-col">
                                                    <span>
                                                        {campaign.segment ??
                                                            campaign.audience ??
                                                            'Not set'}
                                                    </span>
                                                    <span className="text-muted-foreground tabular-nums">
                                                        {isDraft
                                                            ? 'Not sent'
                                                            : `${campaign.recipient_count.toLocaleString()} ${campaign.recipient_count === 1 ? 'recipient' : 'recipients'}`}
                                                    </span>
                                                </div>
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {isDraft
                                                    ? '—'
                                                    : campaign.delivery_rate ===
                                                        null
                                                      ? 'N/A'
                                                      : `${campaign.delivery_rate}%`}
                                            </TableCell>
                                            <RateCell
                                                campaign={campaign}
                                                metric="open_rate"
                                            />
                                            <TableCell className="text-right tabular-nums">
                                                {isDraft ? (
                                                    '—'
                                                ) : (
                                                    <span className="inline-flex items-center justify-end gap-2">
                                                        {isBest && (
                                                            <Badge variant="success">
                                                                Best
                                                            </Badge>
                                                        )}
                                                        {campaign.click_rate}%
                                                    </span>
                                                )}
                                            </TableCell>
                                            <RateCell
                                                campaign={campaign}
                                                metric="click_to_open_rate"
                                            />
                                            {series.primary_cta_url && (
                                                <TableCell className="text-right tabular-nums">
                                                    {isDraft
                                                        ? '—'
                                                        : campaign.cta_clicks.toLocaleString()}
                                                </TableCell>
                                            )}
                                            <TableCell className="text-muted-foreground">
                                                {campaign.sent_at
                                                    ? formatRelativeTime(
                                                          campaign.sent_at,
                                                      )
                                                    : '—'}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger
                                                        render={
                                                            <Button
                                                                size="icon"
                                                                variant="ghost"
                                                                aria-label={`Actions for ${campaign.name}`}
                                                            />
                                                        }
                                                    >
                                                        <HugeiconsIcon
                                                            icon={
                                                                MoreHorizontalIcon
                                                            }
                                                        />
                                                    </DropdownMenuTrigger>
                                                    <DropdownMenuContent align="end">
                                                        <DropdownMenuGroup>
                                                            <DropdownMenuItem
                                                                render={
                                                                    <Link
                                                                        href={campaignRoute(
                                                                            currentTeam.slug,
                                                                            campaign,
                                                                        )}
                                                                        prefetch
                                                                    />
                                                                }
                                                            >
                                                                <HugeiconsIcon
                                                                    icon={
                                                                        isDraft
                                                                            ? Edit03Icon
                                                                            : PieChartIcon
                                                                    }
                                                                />
                                                                {isDraft
                                                                    ? canManage
                                                                        ? 'Edit'
                                                                        : 'View'
                                                                    : 'View report'}
                                                            </DropdownMenuItem>
                                                            {canManage && (
                                                                <>
                                                                    <DropdownMenuSeparator />
                                                                    <DropdownMenuItem
                                                                        variant="destructive"
                                                                        onClick={() =>
                                                                            router.delete(
                                                                                removeCampaign.url(
                                                                                    [
                                                                                        currentTeam.slug,
                                                                                        series.uuid,
                                                                                        campaign.uuid,
                                                                                    ],
                                                                                ),
                                                                                {
                                                                                    preserveScroll: true,
                                                                                },
                                                                            )
                                                                        }
                                                                    >
                                                                        <HugeiconsIcon
                                                                            icon={
                                                                                FolderRemoveIcon
                                                                            }
                                                                        />
                                                                        Remove
                                                                        from
                                                                        series
                                                                    </DropdownMenuItem>
                                                                </>
                                                            )}
                                                        </DropdownMenuGroup>
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
                                            </TableCell>
                                        </TableRow>
                                    );
                                })}
                            </TableBody>
                        </Table>
                    )}
                </section>
            </div>

            {canManage && (
                <>
                    <CampaignSeriesDialog
                        key={`${series.uuid}-${series.updated_at}`}
                        teamSlug={currentTeam.slug}
                        goals={goals}
                        series={series}
                        open={editOpen}
                        onOpenChange={setEditOpen}
                    />
                    <AddCampaignsDialog
                        teamSlug={currentTeam.slug}
                        seriesUuid={series.uuid}
                        campaigns={availableCampaigns}
                        open={addOpen}
                        onOpenChange={setAddOpen}
                    />
                    <EmailTemplatePicker
                        teamSlug={currentTeam.slug}
                        templates={templates}
                        defaultEditor={defaultEditor}
                        campaignSeries={series.uuid}
                        open={composeOpen}
                        onOpenChange={setComposeOpen}
                        title="Compose in this series"
                        description={`Create a campaign inside ${series.name}.`}
                    />
                </>
            )}

            <AlertDialog open={deleteOpen} onOpenChange={setDeleteOpen}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>
                            Delete campaign series?
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            The series and its shared goal will be removed. Its{' '}
                            {summary.campaigns} campaigns and all their reports
                            will be kept.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel disabled={deleting}>
                            Cancel
                        </AlertDialogCancel>
                        <AlertDialogAction
                            variant="destructive"
                            disabled={deleting}
                            onClick={deleteCampaignSeries}
                        >
                            {deleting && <Spinner data-icon="inline-start" />}
                            Delete series
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </>
    );
}

function RateCell({
    campaign,
    metric,
}: {
    campaign: CampaignSeriesCampaign;
    metric: 'open_rate' | 'click_to_open_rate';
}) {
    return (
        <TableCell className="text-right tabular-nums">
            {campaign.status === 'draft' ? '—' : `${campaign[metric]}%`}
        </TableCell>
    );
}

function campaignRoute(
    teamSlug: string,
    campaign: CampaignSeriesCampaign,
): string {
    return campaign.status === 'draft'
        ? editCampaign.url([teamSlug, campaign.uuid])
        : showCampaign.url([teamSlug, campaign.uuid]);
}

function AddCampaignsDialog({
    teamSlug,
    seriesUuid,
    campaigns,
    open,
    onOpenChange,
}: {
    teamSlug: string;
    seriesUuid: string;
    campaigns: AvailableSeriesCampaign[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const form = useForm<{ campaign_uuids: string[] }>({
        campaign_uuids: [],
    });
    const [search, setSearch] = useState('');
    const filteredCampaigns = useMemo(() => {
        const query = search.trim().toLocaleLowerCase();

        return query === ''
            ? campaigns
            : campaigns.filter((campaign) =>
                  `${campaign.name} ${campaign.subject}`
                      .toLocaleLowerCase()
                      .includes(query),
              );
    }, [campaigns, search]);

    const close = (nextOpen: boolean) => {
        if (!nextOpen) {
            form.reset();
            form.clearErrors();
            setSearch('');
        }

        onOpenChange(nextOpen);
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(addCampaigns.url([teamSlug, seriesUuid]), {
            preserveScroll: true,
            onSuccess: () => close(false),
        });
    };

    const toggleCampaign = (uuid: string, checked: boolean) => {
        form.setData(
            'campaign_uuids',
            checked
                ? [...form.data.campaign_uuids, uuid]
                : form.data.campaign_uuids.filter((item) => item !== uuid),
        );
    };

    return (
        <Dialog open={open} onOpenChange={close}>
            <DialogContent className="max-h-[85vh] overflow-y-auto">
                <form onSubmit={submit}>
                    <DialogHeader>
                        <DialogTitle>Add existing campaigns</DialogTitle>
                        <DialogDescription>
                            Only campaigns that are not already in another
                            series are available.
                        </DialogDescription>
                    </DialogHeader>

                    {campaigns.length > 0 ? (
                        <FieldGroup className="mt-4">
                            <Field>
                                <Input
                                    type="search"
                                    value={search}
                                    onChange={(event) =>
                                        setSearch(event.target.value)
                                    }
                                    placeholder="Search campaigns"
                                    aria-label="Search campaigns"
                                />
                            </Field>
                            <FieldSet>
                                <FieldLegend>Campaigns</FieldLegend>
                                <FieldDescription>
                                    Select up to 100 campaigns to add.
                                </FieldDescription>
                                <FieldGroup className="gap-3">
                                    {filteredCampaigns.map((campaign) => (
                                        <Field
                                            key={campaign.uuid}
                                            orientation="horizontal"
                                        >
                                            <Checkbox
                                                id={`campaign-${campaign.uuid}`}
                                                checked={form.data.campaign_uuids.includes(
                                                    campaign.uuid,
                                                )}
                                                onCheckedChange={(checked) =>
                                                    toggleCampaign(
                                                        campaign.uuid,
                                                        checked === true,
                                                    )
                                                }
                                            />
                                            <FieldContent>
                                                <FieldTitle>
                                                    {campaign.name}
                                                </FieldTitle>
                                                <FieldDescription>
                                                    {campaign.subject}
                                                </FieldDescription>
                                            </FieldContent>
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
                                        </Field>
                                    ))}
                                    {filteredCampaigns.length === 0 && (
                                        <p className="text-sm text-muted-foreground">
                                            No campaigns match this search.
                                        </p>
                                    )}
                                </FieldGroup>
                                <FieldError>
                                    {form.errors.campaign_uuids}
                                </FieldError>
                            </FieldSet>
                        </FieldGroup>
                    ) : (
                        <p className="mt-4 text-sm text-muted-foreground">
                            Every campaign is already in a series. Compose a new
                            campaign here or remove one from another series.
                        </p>
                    )}

                    <DialogFooter className="mt-6 gap-2">
                        <DialogClose
                            render={
                                <Button type="button" variant="secondary" />
                            }
                        >
                            Cancel
                        </DialogClose>
                        <Button
                            type="submit"
                            disabled={
                                form.processing ||
                                form.data.campaign_uuids.length === 0
                            }
                        >
                            {form.processing && (
                                <Spinner data-icon="inline-start" />
                            )}
                            {form.data.campaign_uuids.length === 0
                                ? 'Add campaigns'
                                : `Add ${form.data.campaign_uuids.length} ${
                                      form.data.campaign_uuids.length === 1
                                          ? 'campaign'
                                          : 'campaigns'
                                  }`}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
