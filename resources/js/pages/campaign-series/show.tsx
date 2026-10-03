import {
    Add01Icon,
    ArrowLeft02Icon,
    ArrowUpRight01Icon,
    Delete02Icon,
    Edit03Icon,
    FoldersIcon,
    MailOpen01Icon,
    MailSend01Icon,
    MouseLeftClick01Icon,
    Target02Icon,
    UserGroupIcon,
} from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import type { FormEvent } from 'react';
import { CampaignSeriesDialog } from '@/components/campaign-series-dialog';
import EmailTemplatePicker from '@/components/email-template-picker';
import { MetricCard } from '@/components/metric-card';
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
import { cn } from '@/lib/utils';
import {
    destroy as destroySeries,
    index,
    show as showSeries,
} from '@/routes/campaign_series';
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
    const bestClickRate = Math.max(
        0,
        ...report.campaigns
            .filter((campaign) => campaign.status !== 'draft')
            .map((campaign) => campaign.click_rate),
    );

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
                        {series.objective && (
                            <p className="text-sm font-medium">
                                {series.objective}
                            </p>
                        )}
                        {series.description && (
                            <p className="max-w-3xl text-sm text-muted-foreground">
                                {series.description}
                            </p>
                        )}
                        {series.primary_cta_url && (
                            <a
                                href={series.primary_cta_url}
                                target="_blank"
                                rel="noreferrer"
                                className="group inline-flex w-fit items-center gap-1 text-sm text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
                            >
                                Primary CTA
                                <HugeiconsIcon
                                    icon={ArrowUpRight01Icon}
                                    className="transition-transform motion-safe:group-hover:translate-x-0.5 motion-safe:group-hover:-translate-y-0.5"
                                />
                            </a>
                        )}
                    </div>

                    {canManage && (
                        <div className="flex flex-wrap gap-2">
                            <Button
                                variant="outline"
                                onClick={() => setEditOpen(true)}
                            >
                                <HugeiconsIcon
                                    icon={Edit03Icon}
                                    data-icon="inline-start"
                                />
                                Edit
                            </Button>
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
                        </div>
                    )}
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                    <MetricCard
                        label="Unique reach"
                        value={report.summary.unique_recipients.toLocaleString()}
                        detail={`${report.summary.sent_campaigns} of ${report.summary.campaigns} campaigns sent`}
                        icon={UserGroupIcon}
                        iconClassName="bg-violet-500/10 text-violet-600 dark:text-violet-400"
                    />
                    <MetricCard
                        label="Unique open rate"
                        value={`${report.summary.open_rate}%`}
                        detail={`${report.summary.opened.toLocaleString()} people opened`}
                        icon={MailOpen01Icon}
                        iconClassName="bg-amber-500/10 text-amber-600 dark:text-amber-400"
                    />
                    <MetricCard
                        label="Unique click rate"
                        value={`${report.summary.click_rate}%`}
                        detail={`${report.summary.clicked.toLocaleString()} people clicked`}
                        icon={MouseLeftClick01Icon}
                        iconClassName="bg-rose-500/10 text-rose-600 dark:text-rose-400"
                    />
                    <MetricCard
                        label="Click-to-open rate"
                        value={`${report.summary.click_to_open_rate}%`}
                        detail="Clicks among people who opened"
                        icon={MailSend01Icon}
                        iconClassName="bg-blue-500/10 text-blue-600 dark:text-blue-400"
                    />
                    <MetricCard
                        label={
                            series.primary_cta_url
                                ? 'Primary CTA clicks'
                                : 'Processed sends'
                        }
                        value={(series.primary_cta_url
                            ? report.summary.cta_clicks
                            : report.summary.processed
                        ).toLocaleString()}
                        detail={
                            series.primary_cta_url
                                ? 'All tracked clicks on the exact CTA URL'
                                : 'Add a primary CTA URL to track the sales action'
                        }
                        icon={Target02Icon}
                        iconClassName="bg-emerald-500/10 text-emerald-600 dark:text-emerald-400"
                    />
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

                <Card>
                    <CardHeader>
                        <CardTitle>Campaign comparison</CardTitle>
                        <CardDescription>
                            Opens and clicks come from each campaign&apos;s
                            tracking aggregates. The overview above deduplicates
                            people who received more than one campaign in this
                            series.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {report.campaigns.length === 0 ? (
                            <Empty>
                                <EmptyHeader>
                                    <EmptyMedia variant="icon">
                                        <HugeiconsIcon icon={FoldersIcon} />
                                    </EmptyMedia>
                                    <EmptyTitle>
                                        No campaigns in this series
                                    </EmptyTitle>
                                    <EmptyDescription>
                                        Add an existing campaign or compose a
                                        new one for this sales goal.
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
                                                onClick={() =>
                                                    setComposeOpen(true)
                                                }
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
                                            Reach
                                        </TableHead>
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
                                        {canManage && (
                                            <TableHead className="text-right">
                                                Action
                                            </TableHead>
                                        )}
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {report.campaigns.map((campaign) => (
                                        <TableRow
                                            key={campaign.uuid}
                                            data-test="series-campaign-row"
                                        >
                                            <TableCell>
                                                <div className="flex min-w-52 flex-col gap-1">
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
                                                        {bestClickRate > 0 &&
                                                            campaign.click_rate ===
                                                                bestClickRate && (
                                                                <Badge variant="outline">
                                                                    Best click
                                                                    rate
                                                                </Badge>
                                                            )}
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
                                                    {campaign.segment && (
                                                        <span className="text-muted-foreground">
                                                            {campaign.audience}
                                                        </span>
                                                    )}
                                                </div>
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {campaign.recipient_count.toLocaleString()}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {campaign.delivery_rate === null
                                                    ? 'N/A'
                                                    : `${campaign.delivery_rate}%`}
                                            </TableCell>
                                            <RateCell
                                                campaign={campaign}
                                                metric="open_rate"
                                            />
                                            <RateCell
                                                campaign={campaign}
                                                metric="click_rate"
                                            />
                                            <RateCell
                                                campaign={campaign}
                                                metric="click_to_open_rate"
                                            />
                                            {series.primary_cta_url && (
                                                <TableCell className="text-right tabular-nums">
                                                    {campaign.cta_clicks.toLocaleString()}
                                                </TableCell>
                                            )}
                                            <TableCell className="text-muted-foreground">
                                                {campaign.sent_at
                                                    ? formatRelativeTime(
                                                          campaign.sent_at,
                                                      )
                                                    : '—'}
                                            </TableCell>
                                            {canManage && (
                                                <TableCell className="text-right">
                                                    <Button
                                                        size="sm"
                                                        variant="ghost"
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
                                                        Remove
                                                    </Button>
                                                </TableCell>
                                            )}
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>

                <Card size="sm">
                    <CardHeader>
                        <CardTitle>Delivery health</CardTitle>
                        <CardDescription>
                            Operational totals across every campaign in this
                            series.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        <HealthStat
                            label="Bounced"
                            value={report.summary.bounced}
                        />
                        <HealthStat
                            label="Complaints"
                            value={report.summary.complained}
                        />
                        <HealthStat
                            label="Failed"
                            value={report.summary.failed}
                        />
                        <HealthStat
                            label="Delivered"
                            value={report.summary.delivered}
                            detail={
                                report.summary.delivery_rate === null
                                    ? 'Provider feedback unavailable'
                                    : `${report.summary.delivery_rate}% of ${report.summary.feedback_recipient_count.toLocaleString()} feedback-enabled sends`
                            }
                        />
                    </CardContent>
                </Card>

                {canManage && (
                    <div className="flex justify-end">
                        <Button
                            variant="destructive"
                            onClick={() => setDeleteOpen(true)}
                        >
                            <HugeiconsIcon
                                icon={Delete02Icon}
                                data-icon="inline-start"
                            />
                            Delete series
                        </Button>
                    </div>
                )}
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
                            {report.summary.campaigns} campaigns and all their
                            reports will be kept.
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

function HealthStat({
    label,
    value,
    detail,
}: {
    label: string;
    value: number;
    detail?: string;
}) {
    return (
        <div className="flex flex-col gap-1">
            <p className="text-xs text-muted-foreground">{label}</p>
            <p
                className={cn(
                    'text-2xl font-semibold tabular-nums',
                    label !== 'Delivered' && value > 0 && 'text-destructive',
                )}
            >
                {value.toLocaleString()}
            </p>
            {detail && (
                <p className="text-xs text-muted-foreground">{detail}</p>
            )}
        </div>
    );
}

function RateCell({
    campaign,
    metric,
}: {
    campaign: CampaignSeriesCampaign;
    metric: 'open_rate' | 'click_rate' | 'click_to_open_rate';
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
                                <FieldLegend variant="label">
                                    Campaigns
                                </FieldLegend>
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

CampaignSeriesShow.layout = (
    props: CampaignSeriesShowProps & {
        currentTeam?: { slug: string } | null;
    },
) => ({
    breadcrumbs: [
        {
            title: 'Campaign series',
            href: props.currentTeam ? index(props.currentTeam.slug) : '/',
        },
        {
            title: props.series.name,
            href: props.currentTeam
                ? showSeries([props.currentTeam.slug, props.series.uuid])
                : '/',
        },
    ],
});
