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
} from '@/components/email-report-layout';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { cn } from '@/lib/utils';

type Props = {
    campaign: CampaignReportCampaign;
    metrics: CampaignReportMetrics;
    insights: CampaignInsightsData;
    canManage: boolean;
};

export default function EmailShow({
    campaign,
    metrics,
    insights,
    canManage,
}: Props) {
    return (
        <EmailReportLayout
            campaign={campaign}
            metrics={metrics}
            canManage={canManage}
            activePage="overview"
            pollProps={['campaign', 'metrics', 'insights']}
        >
            <div className="flex flex-col gap-4">
                <CampaignInsights insights={insights} />
                <Card size="sm">
                    <CardHeader className="border-b">
                        <CardTitle>Delivery health</CardTitle>
                        <CardDescription>
                            Failed sends can be retried. Permanent bounces and
                            complaints unsubscribe the recipient so they are
                            skipped next time.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        <HealthStat
                            label="Failed"
                            value={metrics.failed}
                            icon={Cancel01Icon}
                            tone="danger"
                        />
                        <HealthStat
                            label="Bounced"
                            value={metrics.bounced}
                            icon={MailRemove01Icon}
                            tone="danger"
                        />
                        <HealthStat
                            label="Complaints"
                            value={metrics.complained}
                            icon={Alert02Icon}
                            tone="danger"
                        />
                        <HealthStat
                            label="Pending"
                            value={Math.max(
                                campaign.recipient_count - metrics.processed,
                                0,
                            )}
                            icon={Clock01Icon}
                        />
                    </CardContent>
                </Card>
            </div>
        </EmailReportLayout>
    );
}

function HealthStat({
    label,
    value,
    icon,
    tone = 'default',
}: {
    label: string;
    value: number;
    icon: IconSvgElement;
    tone?: 'default' | 'danger';
}) {
    const isAlert = tone === 'danger' && value > 0;

    return (
        <div className="flex items-start gap-3">
            <span
                className={cn(
                    'flex size-8 shrink-0 items-center justify-center rounded-lg',
                    isAlert
                        ? 'bg-destructive/10 text-destructive'
                        : 'bg-muted text-muted-foreground',
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
                <p
                    className={cn(
                        'text-2xl font-semibold tabular-nums',
                        isAlert && 'text-destructive',
                    )}
                >
                    {value.toLocaleString()}
                </p>
            </div>
        </div>
    );
}
