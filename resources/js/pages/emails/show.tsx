import {
    Alert02Icon,
    Cancel01Icon,
    Clock01Icon,
    MailRemove01Icon,
} from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import type { IconSvgElement } from '@hugeicons/react';
import { CampaignInsights } from '@/components/campaign-insights';
import type { CampaignInsightsData } from '@/components/campaign-insights';
import { EmailReportLayout } from '@/components/email-report-layout';
import type {
    CampaignReportCampaign,
    CampaignReportMetrics,
    CampaignSendRun,
} from '@/components/email-report-layout';
import { MetricGauge } from '@/components/metric-gauge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Progress, ProgressLabel } from '@/components/ui/progress';
import { formatRelativeTime } from '@/lib/format';
import { cn } from '@/lib/utils';

type Props = {
    campaign: CampaignReportCampaign;
    metrics: CampaignReportMetrics;
    insights: CampaignInsightsData;
    sendRuns: CampaignSendRun[];
    failureCauses: { code: string; label: string; count: number }[];
    canManage: boolean;
};

export default function EmailShow({
    campaign,
    metrics,
    insights,
    sendRuns,
    failureCauses,
    canManage,
}: Props) {
    const feedbackReported = metrics.delivery_feedback !== 'unavailable';

    return (
        <EmailReportLayout
            campaign={campaign}
            metrics={metrics}
            canManage={canManage}
            activePage="overview"
            pollProps={[
                'campaign',
                'metrics',
                'insights',
                'sendRuns',
                'failureCauses',
            ]}
        >
            <div className="flex flex-col gap-4">
                <Card size="sm">
                    <CardHeader>
                        <CardTitle>Delivery health</CardTitle>
                        <CardDescription>
                            Sending progress and confirmed delivery at a glance.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid items-center gap-6 p-5 sm:p-6 lg:grid-cols-[14rem_minmax(0,1fr)] lg:gap-8">
                        <MetricGauge
                            value={metrics.delivery_rate}
                            label="Confirmed delivery"
                            detail={
                                metrics.delivery_rate === null
                                    ? 'Delivery confirmation unavailable'
                                    : `${metrics.delivered.toLocaleString()} of ${metrics.feedback_recipient_count.toLocaleString()} confirmed`
                            }
                            className="justify-self-center"
                        />
                        <div className="flex min-w-0 flex-col gap-6">
                            <Progress
                                value={metrics.progress}
                                className="gap-3 [&_[data-slot=progress-indicator]]:rounded-full [&_[data-slot=progress-indicator]]:transition-[width] [&_[data-slot=progress-indicator]]:motion-reduce:transition-none [&_[data-slot=progress-track]]:h-2.5"
                            >
                                <ProgressLabel>
                                    Recipients processed
                                </ProgressLabel>
                                <span className="ml-auto text-sm text-muted-foreground tabular-nums">
                                    {metrics.processed.toLocaleString()} of{' '}
                                    {campaign.recipient_count.toLocaleString()}
                                </span>
                            </Progress>
                            <div className="grid grid-cols-2 gap-x-4 gap-y-5 sm:grid-cols-4">
                                <HealthStat
                                    label="Failed"
                                    value={metrics.failed}
                                    icon={Cancel01Icon}
                                    iconClassName="bg-rose-500/10 text-rose-600 dark:text-rose-400"
                                    tone="danger"
                                />
                                <HealthStat
                                    label="Bounced"
                                    value={
                                        feedbackReported
                                            ? metrics.bounced
                                            : null
                                    }
                                    icon={MailRemove01Icon}
                                    iconClassName="bg-orange-500/10 text-orange-600 dark:text-orange-400"
                                    tone="danger"
                                />
                                <HealthStat
                                    label="Complaints"
                                    value={
                                        feedbackReported
                                            ? metrics.complained
                                            : null
                                    }
                                    icon={Alert02Icon}
                                    iconClassName="bg-amber-500/10 text-amber-600 dark:text-amber-400"
                                    tone="danger"
                                />
                                <HealthStat
                                    label="Pending"
                                    value={Math.max(
                                        campaign.recipient_count -
                                            metrics.processed,
                                        0,
                                    )}
                                    icon={Clock01Icon}
                                    iconClassName="bg-blue-500/10 text-blue-600 dark:text-blue-400"
                                />
                            </div>
                            <p className="text-xs/relaxed text-muted-foreground">
                                Failed sends can be retried. Permanent bounces
                                and complaints are left unsubscribed.
                                {metrics.delivery_feedback === 'partial' && (
                                    <>
                                        {' '}
                                        Delivery confirmation covers{' '}
                                        {metrics.feedback_recipient_count.toLocaleString()}{' '}
                                        of{' '}
                                        {campaign.recipient_count.toLocaleString()}{' '}
                                        recipients.
                                    </>
                                )}
                            </p>
                        </div>
                    </CardContent>
                    {failureCauses.length > 0 ? (
                        <CardContent
                            className="border-t pt-4"
                            data-test="campaign-failure-causes"
                        >
                            <p className="mb-2 text-sm font-medium">
                                Top causes
                            </p>
                            <ul className="flex flex-col gap-1 text-sm">
                                {failureCauses.map((cause) => (
                                    <li
                                        key={cause.code}
                                        className="flex items-center justify-between gap-4"
                                    >
                                        <span className="text-muted-foreground">
                                            {cause.label}
                                        </span>
                                        <span className="font-medium tabular-nums">
                                            {cause.count.toLocaleString()}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        </CardContent>
                    ) : null}
                </Card>
                {sendRuns.length > 1 && <SendHistory runs={sendRuns} />}
                <CampaignInsights insights={insights} />
            </div>
        </EmailReportLayout>
    );
}

/**
 * Lists the first send and every retry, newest first, so a retry reads as
 * its own pass instead of silently reopening the original send.
 */
function SendHistory({ runs }: { runs: CampaignSendRun[] }) {
    return (
        <Card size="sm" data-test="campaign-send-history">
            <CardHeader className="border-b">
                <CardTitle>Send history</CardTitle>
                <CardDescription>
                    The first send and each retry of failed deliveries.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <ul className="flex flex-col divide-y">
                    {runs.map((run) => (
                        <li
                            key={run.started_at}
                            className="flex flex-wrap items-center justify-between gap-2 py-2 text-sm"
                        >
                            <span className="font-medium">
                                {run.kind === 'retry'
                                    ? 'Retry'
                                    : run.kind === 'resume'
                                      ? 'Remaining recipients'
                                      : 'First send'}
                            </span>
                            <span className="text-muted-foreground tabular-nums">
                                {run.recipient_count.toLocaleString()}{' '}
                                {run.recipient_count === 1
                                    ? 'recipient'
                                    : 'recipients'}{' '}
                                · {run.failed.toLocaleString()} failed ·{' '}
                                {run.finished_at
                                    ? `finished ${formatRelativeTime(run.finished_at)} ago`
                                    : `${run.processed.toLocaleString()} processed so far`}
                            </span>
                        </li>
                    ))}
                </ul>
            </CardContent>
        </Card>
    );
}

/**
 * A null value means the transport never reports this outcome (SMTP is
 * handoff only), so the stat says so instead of showing a misleading 0.
 */
function HealthStat({
    label,
    value,
    icon,
    iconClassName,
    tone = 'default',
}: {
    label: string;
    value: number | null;
    icon: IconSvgElement;
    iconClassName: string;
    tone?: 'default' | 'danger';
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
                        Not reported by SMTP
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
