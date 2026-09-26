import {
    ArrowLeft02Icon,
    MailOpen01Icon,
    MailSend01Icon,
    MouseLeftClick01Icon,
    Refresh03Icon,
    UserGroupIcon,
} from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import type { IconSvgElement } from '@hugeicons/react';
import {
    Head,
    Link,
    router,
    setLayoutProps,
    usePage,
    usePoll,
} from '@inertiajs/react';
import { useCallback, useLayoutEffect, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import {
    Alert,
    AlertAction,
    AlertDescription,
    AlertTitle,
} from '@/components/ui/alert';
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
    Card,
    CardAction,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Field, FieldDescription, FieldLabel } from '@/components/ui/field';
import { Spinner } from '@/components/ui/spinner';
import { toast } from '@/components/ui/toast';
import { formatRelativeTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import {
    index,
    links as linksRoute,
    preview as previewRoute,
    recipients,
    retry,
    show,
} from '@/routes/emails';
import type { EmailCampaignStatus } from '@/types/emails';

export type CampaignEmailProvider =
    'smtp' | 'ses' | 'sendgrid' | 'mailgun' | 'resend' | 'postmark' | 'mixed';

export type CampaignReportCampaign = {
    uuid: string;
    name: string;
    subject: string;
    preheader: string | null;
    html: string;
    status: EmailCampaignStatus;
    provider: CampaignEmailProvider | null;
    uses_team_email_integration: boolean;
    audience: string | null;
    segment: string | null;
    recipient_count: number;
    send_started_at: string | null;
    sent_at: string | null;
};

export type CampaignReportMetrics = {
    processed: number;
    progress: number;
    delivered: number;
    opened: number;
    clicked: number;
    bounced: number;
    complained: number;
    failed: number;
    /** Queued deliveries whose last send attempt failed; the queue tries them again. */
    retrying: number;
    retryable: number;
    /** Retryable deliveries that were handed to the provider but never confirmed. */
    unconfirmed: number;
    delivery_feedback: 'available' | 'partial' | 'unavailable';
    feedback_recipient_count: number;
    delivery_rate: number | null;
    open_rate: number;
    click_rate: number;
};

export type CampaignReportRecipient = {
    uuid: string;
    avatar: string | null;
    email: string;
    name: string | null;
    status: CampaignRecipientStatus;
    opens: number;
    clicks: number;
    failure_reason: string | null;
    sent_at: string | null;
    can_retry: boolean;
    /** Why this row cannot be retried; null when there is nothing to retry. */
    retry_blocked_reason: string | null;
    unconfirmed: boolean;
};

export type CampaignRecipientStatus =
    | 'queued'
    | 'sending'
    | 'sent'
    | 'delivered'
    | 'delayed'
    | 'bounced'
    | 'complained'
    | 'rejected'
    | 'failed';

export type CampaignTrackedLink = {
    uuid: string;
    url: string;
    clicks: number;
};

export type CampaignReportPage =
    'overview' | 'recipients' | 'links' | 'preview';

type Props = {
    campaign: CampaignReportCampaign;
    metrics: CampaignReportMetrics;
    canManage: boolean;
    activePage: CampaignReportPage;
    pollProps: string[];
    children: ReactNode;
};

const CAMPAIGN_LABELS: Record<EmailCampaignStatus, string> = {
    draft: 'Draft',
    queued: 'Queued',
    sending: 'Sending',
    sent: 'Sent',
    partially_failed: 'Partially failed',
    failed: 'Failed',
};

const EMAIL_PROVIDER_LABELS: Record<CampaignEmailProvider, string> = {
    smtp: 'SMTP',
    ses: 'Amazon SES',
    sendgrid: 'SendGrid',
    mailgun: 'Mailgun',
    resend: 'Resend',
    postmark: 'Postmark',
    mixed: 'Mixed providers',
};

const REPORT_PAGES: {
    key: CampaignReportPage;
    label: string;
    href: (teamSlug: string, campaignUuid: string) => string;
}[] = [
    {
        key: 'overview',
        label: 'Overview',
        href: (teamSlug, campaignUuid) => show.url([teamSlug, campaignUuid]),
    },
    {
        key: 'recipients',
        label: 'Recipients',
        href: (teamSlug, campaignUuid) =>
            recipients.url([teamSlug, campaignUuid]),
    },
    {
        key: 'links',
        label: 'Links',
        href: (teamSlug, campaignUuid) =>
            linksRoute.url([teamSlug, campaignUuid]),
    },
    {
        key: 'preview',
        label: 'Preview',
        href: (teamSlug, campaignUuid) =>
            previewRoute.url([teamSlug, campaignUuid]),
    },
];

export function EmailReportLayout({
    campaign,
    metrics,
    canManage,
    activePage,
    pollProps,
    children,
}: Props) {
    const { currentTeam } = usePage().props;
    const [retryOpen, setRetryOpen] = useState(false);
    const [retrying, setRetrying] = useState(false);
    const [includeUnconfirmed, setIncludeUnconfirmed] = useState(false);

    setLayoutProps({
        fullscreen: false,
        breadcrumbs: currentTeam
            ? [
                  {
                      title: 'Campaigns',
                      href: index.url(currentTeam.slug),
                  },
                  {
                      title: campaign.name,
                      href: show.url([currentTeam.slug, campaign.uuid]),
                  },
              ]
            : [],
    });
    const isActive =
        campaign.status === 'queued' || campaign.status === 'sending';
    const providerLabel = campaign.provider
        ? EMAIL_PROVIDER_LABELS[campaign.provider]
        : null;
    const deliveryFeedbackDetail =
        metrics.delivery_feedback === 'available'
            ? `${metrics.delivered.toLocaleString()} of ${metrics.feedback_recipient_count.toLocaleString()} confirmed by SES`
            : metrics.delivery_feedback === 'partial'
              ? `${metrics.feedback_recipient_count.toLocaleString()} of ${campaign.recipient_count.toLocaleString()} recipients were sent via SES; other transports do not report delivery feedback`
              : campaign.provider === 'smtp'
                ? 'SMTP is handoff only and does not report delivery feedback'
                : 'Delivery feedback is unavailable for this transport';

    if (!currentTeam) {
        return null;
    }

    const canRetryFailed = canManage && !isActive && metrics.retryable > 0;
    const retryCount = includeUnconfirmed
        ? metrics.retryable
        : metrics.retryable - metrics.unconfirmed;
    const openRetry = () => {
        setIncludeUnconfirmed(false);
        setRetryOpen(true);
    };

    return (
        <>
            <Head title={`${campaign.name} report`} />
            {isActive && <CampaignPoller only={pollProps} />}
            <div className="flex flex-1 flex-col gap-6">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                    <div className="flex flex-col gap-2">
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
                            Campaigns
                        </Button>
                        <div className="flex flex-wrap items-center gap-2">
                            <h1 className="text-2xl font-semibold tracking-tight">
                                {campaign.name}
                            </h1>
                            <Badge
                                variant={
                                    campaign.status === 'sent'
                                        ? 'success'
                                        : campaign.status === 'failed' ||
                                            campaign.status ===
                                                'partially_failed'
                                          ? 'destructive'
                                          : 'info'
                                }
                            >
                                {CAMPAIGN_LABELS[campaign.status]}
                            </Badge>
                            <Badge variant="outline">{providerLabel}</Badge>
                        </div>
                        <p className="text-sm text-muted-foreground">
                            {campaign.subject} ·{' '}
                            {campaign.segment ?? campaign.audience}
                        </p>
                    </div>
                    <div className="flex flex-col items-start gap-3 sm:items-end">
                        <p className="text-sm text-muted-foreground">
                            {campaign.sent_at
                                ? `Completed ${formatRelativeTime(campaign.sent_at)}`
                                : campaign.send_started_at
                                  ? `Started ${formatRelativeTime(campaign.send_started_at)}`
                                  : 'Preparing delivery'}
                        </p>
                        {canRetryFailed && (
                            <Button
                                type="button"
                                variant="outline"
                                data-test="retry-failed-button"
                                onClick={openRetry}
                            >
                                <HugeiconsIcon
                                    icon={Refresh03Icon}
                                    data-icon="inline-start"
                                />
                                Retry failed
                            </Button>
                        )}
                    </div>
                </div>

                {isActive && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Sending campaign</CardTitle>
                            <CardDescription>
                                {metrics.processed} of{' '}
                                {campaign.recipient_count} recipients processed.
                                This report refreshes automatically.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-3">
                            <div className="h-2 overflow-hidden rounded-full bg-muted">
                                <div
                                    className="h-full rounded-full bg-primary transition-[width]"
                                    style={{ width: `${metrics.progress}%` }}
                                />
                            </div>
                            {(metrics.failed > 0 || metrics.retrying > 0) && (
                                <p
                                    className="text-sm text-muted-foreground tabular-nums"
                                    data-test="sending-failure-counts"
                                >
                                    {metrics.failed.toLocaleString()} failed ·{' '}
                                    {metrics.retrying.toLocaleString()} waiting
                                    to retry
                                </p>
                            )}
                        </CardContent>
                    </Card>
                )}

                {isActive && metrics.failed > 0 && (
                    <Alert variant="warning" data-test="sending-failures-alert">
                        <AlertTitle>
                            {metrics.failed.toLocaleString()}{' '}
                            {metrics.failed === 1
                                ? 'delivery has'
                                : 'deliveries have'}{' '}
                            failed so far
                        </AlertTitle>
                        <AlertDescription>
                            Sending continues for everyone else. Failed
                            deliveries used up their automatic retries; you can
                            retry them from this report once the campaign
                            finishes.
                        </AlertDescription>
                    </Alert>
                )}

                {canRetryFailed && (
                    <Alert
                        variant="destructive"
                        data-test="failed-deliveries-alert"
                    >
                        <AlertTitle>Some deliveries need attention</AlertTitle>
                        <AlertDescription>
                            {metrics.retryable.toLocaleString()}{' '}
                            {metrics.retryable === 1
                                ? 'recipient can be retried'
                                : 'recipients can be retried'}
                            {metrics.unconfirmed > 0 &&
                                `, including ${metrics.unconfirmed.toLocaleString()} unconfirmed that may already have arrived`}
                            . Permanent bounces and complaints are not retried.
                        </AlertDescription>
                        <AlertAction>
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                data-test="retry-failed-alert-button"
                                onClick={openRetry}
                            >
                                Retry
                            </Button>
                        </AlertAction>
                    </Alert>
                )}

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <MetricCard
                        label="Recipients"
                        value={campaign.recipient_count.toLocaleString()}
                        detail={`${metrics.processed.toLocaleString()} processed`}
                        icon={UserGroupIcon}
                    />
                    <MetricCard
                        label="Delivered"
                        value={
                            metrics.delivery_rate === null
                                ? '—'
                                : `${metrics.delivery_rate}%`
                        }
                        detail={deliveryFeedbackDetail}
                        icon={MailSend01Icon}
                    />
                    <MetricCard
                        label="Unique opens"
                        value={`${metrics.open_rate}%`}
                        detail={`${metrics.opened.toLocaleString()} recipients`}
                        icon={MailOpen01Icon}
                    />
                    <MetricCard
                        label="Unique clicks"
                        value={`${metrics.click_rate}%`}
                        detail={`${metrics.clicked.toLocaleString()} recipients`}
                        icon={MouseLeftClick01Icon}
                    />
                </div>

                <CampaignReportNav
                    teamSlug={currentTeam.slug}
                    campaignUuid={campaign.uuid}
                    activePage={activePage}
                />

                {children}
            </div>

            <AlertDialog open={retryOpen} onOpenChange={setRetryOpen}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>
                            Retry failed deliveries?
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            {retryCount}{' '}
                            {retryCount === 1 ? 'recipient' : 'recipients'} with
                            a failed, rejected, or delayed send will be queued
                            again. Permanent bounces and complaints are left
                            unsubscribed.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    {metrics.unconfirmed > 0 && (
                        <Field orientation="horizontal">
                            <Checkbox
                                id="retry-include-unconfirmed"
                                data-test="retry-include-unconfirmed"
                                checked={includeUnconfirmed}
                                disabled={retrying}
                                onCheckedChange={(checked) =>
                                    setIncludeUnconfirmed(checked === true)
                                }
                            />
                            <div className="flex flex-col gap-1">
                                <FieldLabel htmlFor="retry-include-unconfirmed">
                                    Also retry {metrics.unconfirmed} unconfirmed{' '}
                                    {metrics.unconfirmed === 1
                                        ? 'delivery'
                                        : 'deliveries'}
                                </FieldLabel>
                                <FieldDescription>
                                    Sending stopped after{' '}
                                    {metrics.unconfirmed === 1
                                        ? 'this message was'
                                        : 'these messages were'}{' '}
                                    handed to your email provider, so{' '}
                                    {metrics.unconfirmed === 1 ? 'it' : 'they'}{' '}
                                    may already have arrived. Retrying can send
                                    a second copy.
                                </FieldDescription>
                            </div>
                        </Field>
                    )}
                    <AlertDialogFooter>
                        <AlertDialogCancel disabled={retrying}>
                            Cancel
                        </AlertDialogCancel>
                        <AlertDialogAction
                            data-test="confirm-retry-failed"
                            disabled={retrying || retryCount === 0}
                            onClick={() =>
                                router.post(
                                    retry.url([
                                        currentTeam.slug,
                                        campaign.uuid,
                                    ]),
                                    { include_unconfirmed: includeUnconfirmed },
                                    {
                                        onStart: () => setRetrying(true),
                                        onError: (errors) =>
                                            toast.add({
                                                type: 'error',
                                                title: 'Could not retry failed deliveries.',
                                                description:
                                                    errors.email ??
                                                    'Nothing was queued. Try again in a moment.',
                                            }),
                                        onFinish: () => {
                                            setRetrying(false);
                                            setRetryOpen(false);
                                        },
                                    },
                                )
                            }
                        >
                            {retrying && <Spinner data-icon="inline-start" />}
                            Retry failed
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </>
    );
}

type ReportIndicator = {
    left: number;
    width: number;
};

let persistedReportIndicator: ReportIndicator | null = null;

function CampaignReportNav({
    teamSlug,
    campaignUuid,
    activePage,
}: {
    teamSlug: string;
    campaignUuid: string;
    activePage: CampaignReportPage;
}) {
    const navRef = useRef<HTMLElement>(null);
    const [isFirstReveal] = useState(() => persistedReportIndicator === null);
    const [indicator, setIndicator] = useState<ReportIndicator | null>(
        persistedReportIndicator,
    );

    const moveTo = useCallback((page: CampaignReportPage) => {
        const nav = navRef.current;
        const target = nav?.querySelector<HTMLElement>(
            `[data-report-page="${page}"]`,
        );

        if (!nav || !target) {
            return;
        }

        const navRect = nav.getBoundingClientRect();
        const targetRect = target.getBoundingClientRect();
        const nextIndicator = {
            left: Math.round(targetRect.left - navRect.left + nav.scrollLeft),
            width: Math.round(targetRect.width),
        };

        persistedReportIndicator = nextIndicator;
        setIndicator((currentIndicator) =>
            currentIndicator?.left === nextIndicator.left &&
            currentIndicator.width === nextIndicator.width
                ? currentIndicator
                : nextIndicator,
        );
    }, []);

    useLayoutEffect(() => {
        moveTo(activePage);

        const nav = navRef.current;

        if (!nav) {
            return;
        }

        const resizeObserver = new ResizeObserver(() => {
            moveTo(activePage);
        });

        resizeObserver.observe(nav);

        for (const link of nav.querySelectorAll('[data-report-page]')) {
            resizeObserver.observe(link);
        }

        return () => {
            resizeObserver.disconnect();
        };
    }, [activePage, moveTo]);

    return (
        <nav
            ref={navRef}
            className="relative flex flex-wrap gap-x-6 overflow-hidden before:pointer-events-none before:absolute before:inset-x-0 before:bottom-0 before:h-px before:bg-border"
            aria-label="Campaign report"
        >
            {indicator && (
                <span
                    aria-hidden="true"
                    className={cn(
                        'pointer-events-none absolute bottom-0 left-0 h-1 rounded-t bg-foreground transition-[transform,width,opacity] duration-300 ease-out motion-reduce:transition-none',
                        isFirstReveal &&
                            'motion-safe:animate-in motion-safe:duration-300 motion-safe:fade-in-0 motion-safe:slide-in-from-bottom-1',
                    )}
                    style={{
                        transform: `translateX(${indicator.left}px)`,
                        width: `${indicator.width}px`,
                    }}
                />
            )}
            {REPORT_PAGES.map((page) => {
                const active = activePage === page.key;

                return (
                    <Link
                        key={page.key}
                        href={page.href(teamSlug, campaignUuid)}
                        prefetch
                        data-report-page={page.key}
                        className={cn(
                            'relative flex items-center px-1 pb-3 text-sm font-medium transition-colors duration-300 ease-out focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none motion-reduce:transition-none',
                            active
                                ? 'font-semibold text-foreground'
                                : "text-muted-foreground after:absolute after:inset-x-0 after:bottom-0 after:h-1 after:translate-y-full after:rounded-t after:bg-border after:transition-transform after:duration-300 after:ease-out after:content-[''] hover:text-foreground hover:after:translate-y-0 focus-visible:after:translate-y-0 motion-reduce:after:transition-none",
                        )}
                        aria-current={active ? 'page' : undefined}
                    >
                        {page.label}
                    </Link>
                );
            })}
        </nav>
    );
}

function MetricCard({
    label,
    value,
    detail,
    icon,
}: {
    label: string;
    value: string;
    detail: string;
    icon: IconSvgElement;
}) {
    return (
        <Card>
            <CardHeader>
                <CardDescription>{label}</CardDescription>
                <CardAction>
                    <div className="flex size-8 items-center justify-center rounded-lg bg-muted text-muted-foreground">
                        <HugeiconsIcon
                            icon={icon}
                            className="size-4"
                            aria-hidden
                        />
                    </div>
                </CardAction>
                <CardTitle className="text-2xl font-semibold tabular-nums">
                    {value}
                </CardTitle>
            </CardHeader>
            <CardContent>
                <p className="text-sm text-muted-foreground">{detail}</p>
            </CardContent>
        </Card>
    );
}

function CampaignPoller({ only }: { only: string[] }) {
    usePoll(2000, { only }, { mode: 'rest' });

    return null;
}
