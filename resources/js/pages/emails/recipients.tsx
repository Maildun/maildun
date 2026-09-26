import { Refresh03Icon } from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { EmailReportLayout } from '@/components/email-report-layout';
import type {
    CampaignReportCampaign,
    CampaignReportMetrics,
    CampaignReportRecipient,
} from '@/components/email-report-layout';
import { Paginator } from '@/components/paginator';
import { RecipientDeliverySheet } from '@/components/recipient-delivery-sheet';
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
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { toast } from '@/components/ui/toast';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import {
    DELIVERY_STATUS_LABELS,
    deliveryStatusVariant,
} from '@/lib/email-status';
import { formatRelativeTime } from '@/lib/format';
import { recipients as recipientsRoute } from '@/routes/emails';
import { retry as retryDelivery } from '@/routes/emails/deliveries';
import type { Paginated } from '@/types/audiences';
import type { CampaignRecipientFilter } from '@/types/emails';

type Props = {
    campaign: CampaignReportCampaign;
    metrics: CampaignReportMetrics;
    filters: { status: CampaignRecipientFilter | null };
    recipients: Paginated<CampaignReportRecipient>;
    canManage: boolean;
};

const RECIPIENT_FILTERS = [
    { value: 'all', label: 'All' },
    { value: 'opened', label: 'Opened' },
    { value: 'clicked', label: 'Clicked' },
    { value: 'retryable', label: 'Retryable' },
    { value: 'failed', label: 'Failed' },
    { value: 'bounced', label: 'Bounced' },
    { value: 'complained', label: 'Complained' },
] as const;

export default function EmailRecipients({
    campaign,
    metrics,
    filters,
    recipients,
    canManage,
}: Props) {
    const { currentTeam } = usePage().props;
    const [retrying, setRetrying] = useState(false);
    const [detailFor, setDetailFor] = useState<string | null>(null);
    const [confirmingRetry, setConfirmingRetry] =
        useState<CampaignReportRecipient | null>(null);

    if (!currentTeam) {
        return null;
    }

    const retryRecipient = (
        recipient: CampaignReportRecipient,
        includeUnconfirmed: boolean,
    ) =>
        router.post(
            retryDelivery.url([
                currentTeam.slug,
                campaign.uuid,
                recipient.uuid,
            ]),
            { include_unconfirmed: includeUnconfirmed },
            {
                preserveScroll: true,
                onStart: () => setRetrying(true),
                onError: (errors) =>
                    toast.add({
                        type: 'error',
                        title: `Could not retry ${recipient.email}.`,
                        description:
                            errors.email ??
                            'Nothing was queued. Try again in a moment.',
                    }),
                onFinish: () => {
                    setRetrying(false);
                    setConfirmingRetry(null);
                },
            },
        );

    return (
        <EmailReportLayout
            campaign={campaign}
            metrics={metrics}
            canManage={canManage}
            activePage="recipients"
            pollProps={['campaign', 'metrics', 'recipients', 'filters']}
        >
            <Card>
                <CardHeader className="gap-4">
                    <div className="flex flex-col gap-1.5">
                        <CardTitle>Recipient activity</CardTitle>
                        <CardDescription>
                            One delivery record per subscribed recipient. Retry
                            failed, delayed, or rejected sends. Permanent
                            bounces stay unsubscribed.
                        </CardDescription>
                    </div>
                    <div className="overflow-x-auto overflow-y-hidden">
                        <RecipientStatusTabs
                            teamSlug={currentTeam.slug}
                            campaignUuid={campaign.uuid}
                            status={filters.status}
                        />
                    </div>
                </CardHeader>
                <CardContent className="flex flex-col gap-4">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Recipient</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Opens</TableHead>
                                <TableHead>Clicks</TableHead>
                                <TableHead>Sent</TableHead>
                                {canManage && (
                                    <TableHead className="text-right">
                                        Actions
                                    </TableHead>
                                )}
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {recipients.data.map((recipient) => (
                                <TableRow key={recipient.uuid}>
                                    <TableCell className="max-w-0">
                                        <div className="flex min-w-0 items-center gap-3">
                                            <Avatar className="size-8">
                                                {recipient.avatar && (
                                                    <AvatarImage
                                                        src={recipient.avatar}
                                                        alt=""
                                                    />
                                                )}
                                                <AvatarFallback>
                                                    {recipientInitial(
                                                        recipient,
                                                    )}
                                                </AvatarFallback>
                                            </Avatar>
                                            <div className="flex min-w-0 flex-col">
                                                <button
                                                    type="button"
                                                    className="truncate text-left font-medium underline-offset-4 hover:underline"
                                                    data-test="open-recipient-detail"
                                                    onClick={() =>
                                                        setDetailFor(
                                                            recipient.uuid,
                                                        )
                                                    }
                                                >
                                                    {recipient.name ??
                                                        recipient.email}
                                                </button>
                                                {recipient.name && (
                                                    <span className="truncate text-muted-foreground">
                                                        {recipient.email}
                                                    </span>
                                                )}
                                                {recipient.failure_reason && (
                                                    <span className="text-xs text-destructive">
                                                        {
                                                            recipient.failure_reason
                                                        }
                                                    </span>
                                                )}
                                            </div>
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <div className="flex flex-wrap items-center gap-1.5">
                                            <Badge
                                                variant={deliveryStatusVariant(
                                                    recipient.status,
                                                )}
                                            >
                                                {
                                                    DELIVERY_STATUS_LABELS[
                                                        recipient.status
                                                    ]
                                                }
                                            </Badge>
                                            {recipient.unconfirmed && (
                                                <Tooltip>
                                                    <TooltipTrigger
                                                        render={
                                                            <Badge
                                                                variant="amber"
                                                                tabIndex={0}
                                                                data-test="recipient-unconfirmed-badge"
                                                            />
                                                        }
                                                    >
                                                        Unconfirmed
                                                    </TooltipTrigger>
                                                    <TooltipContent className="max-w-64">
                                                        <p>
                                                            Sending stopped
                                                            after this message
                                                            was handed to your
                                                            email provider, so
                                                            it may already have
                                                            arrived.
                                                        </p>
                                                    </TooltipContent>
                                                </Tooltip>
                                            )}
                                        </div>
                                    </TableCell>
                                    <TableCell>{recipient.opens}</TableCell>
                                    <TableCell>{recipient.clicks}</TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {recipient.sent_at
                                            ? formatRelativeTime(
                                                  recipient.sent_at,
                                              )
                                            : '—'}
                                    </TableCell>
                                    {canManage && (
                                        <TableCell className="text-right">
                                            {recipient.can_retry ? (
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant="outline"
                                                    data-test="retry-delivery-button"
                                                    disabled={retrying}
                                                    onClick={() =>
                                                        recipient.unconfirmed
                                                            ? setConfirmingRetry(
                                                                  recipient,
                                                              )
                                                            : retryRecipient(
                                                                  recipient,
                                                                  false,
                                                              )
                                                    }
                                                >
                                                    <HugeiconsIcon
                                                        icon={Refresh03Icon}
                                                        data-icon="inline-start"
                                                    />
                                                    Retry
                                                </Button>
                                            ) : recipient.retry_blocked_reason ? (
                                                <Tooltip>
                                                    <TooltipTrigger
                                                        render={
                                                            <span
                                                                tabIndex={0}
                                                                className="cursor-help text-sm text-muted-foreground underline decoration-dotted underline-offset-4"
                                                                data-test="retry-blocked-reason"
                                                            />
                                                        }
                                                    >
                                                        Can't retry
                                                    </TooltipTrigger>
                                                    <TooltipContent className="max-w-64">
                                                        <p>
                                                            {
                                                                recipient.retry_blocked_reason
                                                            }
                                                        </p>
                                                    </TooltipContent>
                                                </Tooltip>
                                            ) : (
                                                <span className="text-muted-foreground">
                                                    —
                                                </span>
                                            )}
                                        </TableCell>
                                    )}
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        {recipients.total > 0 && (
                            <p className="text-sm text-muted-foreground">
                                Showing {recipients.from}–{recipients.to} of{' '}
                                {recipients.total} recipients
                            </p>
                        )}
                        <Paginator paginator={recipients} />
                    </div>
                </CardContent>
            </Card>

            <AlertDialog
                open={confirmingRetry !== null}
                onOpenChange={(open) => {
                    if (!open && !retrying) {
                        setConfirmingRetry(null);
                    }
                }}
            >
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>
                            Retry an unconfirmed delivery?
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            Sending stopped after this message was handed to
                            your email provider, so{' '}
                            {confirmingRetry?.email ?? 'this recipient'} may
                            already have it. Retrying can send them a second
                            copy.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel disabled={retrying}>
                            Cancel
                        </AlertDialogCancel>
                        <AlertDialogAction
                            data-test="confirm-retry-unconfirmed"
                            disabled={retrying}
                            onClick={() =>
                                confirmingRetry &&
                                retryRecipient(confirmingRetry, true)
                            }
                        >
                            {retrying && <Spinner data-icon="inline-start" />}
                            Retry anyway
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
            <RecipientDeliverySheet
                teamSlug={currentTeam.slug}
                campaignUuid={campaign.uuid}
                deliveryUuid={detailFor}
                onClose={() => setDetailFor(null)}
            />
        </EmailReportLayout>
    );
}

function recipientInitial(recipient: CampaignReportRecipient): string {
    return (recipient.name ?? recipient.email).charAt(0).toUpperCase();
}

function RecipientStatusTabs({
    teamSlug,
    campaignUuid,
    status,
}: {
    teamSlug: string;
    campaignUuid: string;
    status: CampaignRecipientFilter | null;
}) {
    return (
        <Tabs
            value={status ?? 'all'}
            onValueChange={(value) => {
                const next = RECIPIENT_FILTERS.find(
                    (filter) => filter.value === value,
                )?.value;

                if (next === undefined) {
                    return;
                }

                router.get(
                    recipientsRoute.url([teamSlug, campaignUuid], {
                        query:
                            next === 'all'
                                ? {}
                                : {
                                      status: next,
                                  },
                    }),
                    {},
                    {
                        preserveState: true,
                        preserveScroll: true,
                        replace: true,
                        only: ['recipients', 'filters'],
                    },
                );
            }}
        >
            <TabsList variant="sliding" aria-label="Filter recipients">
                {RECIPIENT_FILTERS.map((filter) => (
                    <TabsTrigger
                        key={filter.value}
                        value={filter.value}
                        data-test={`recipient-filter-${filter.value}`}
                    >
                        {filter.label}
                    </TabsTrigger>
                ))}
            </TabsList>
        </Tabs>
    );
}
