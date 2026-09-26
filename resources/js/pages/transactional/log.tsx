import { MailSend02Icon, StatusIcon } from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { Head, Link, usePage } from '@inertiajs/react';
import { ActiveFilters } from '@/components/active-filters';
import { FilterMenu } from '@/components/filter-menu';
import { ListSearch } from '@/components/list-search';
import { Paginator } from '@/components/paginator';
import { Badge } from '@/components/ui/badge';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useListFilters } from '@/hooks/use-list-filters';
import {
    DELIVERY_STATUS_LABELS,
    deliveryStatusVariant,
} from '@/lib/email-status';
import { formatRelativeTime } from '@/lib/format';
import { edit, index, log } from '@/routes/transactional_emails';
import type { EmailDeliveryStatus } from '@/types';
import type { Paginated } from '@/types/audiences';

type LogFilters = { q: string; status: string };

type LogDelivery = {
    uuid: string;
    to: string;
    subject: string;
    status: EmailDeliveryStatus;
    failure_reason: string | null;
    /** The API key that requested it; null for automation and double opt-in sends. */
    source: string | null;
    queued_at: string | null;
    sent_at: string | null;
};

type Props = {
    email: { uuid: string; name: string; slug: string };
    filters: LogFilters;
    deliveries: Paginated<LogDelivery>;
};

export default function TransactionalLog({
    email,
    filters,
    deliveries,
}: Props) {
    const { currentTeam } = usePage().props;
    const { visit, clear, clearAll, hasActiveFilters, searchKey } =
        useListFilters<LogFilters>({
            url: currentTeam ? log.url([currentTeam.slug, email.uuid]) : '',
            filters,
            empty: { q: '', status: '' },
        });

    if (!currentTeam) {
        return null;
    }

    return (
        <>
            <Head title={`${email.name} log`} />
            <div className="flex flex-1 flex-col gap-6">
                <div className="flex flex-col gap-1">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Delivery log
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Every message sent from{' '}
                        <Link
                            href={edit([currentTeam.slug, email.uuid])}
                            className="font-medium text-foreground underline-offset-4 hover:underline"
                        >
                            {email.name}
                        </Link>{' '}
                        (<code className="font-mono">{email.slug}</code>),
                        newest first.
                    </p>
                </div>

                {(deliveries.data.length > 0 || hasActiveFilters) && (
                    <div className="flex flex-col gap-3">
                        <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <FilterMenu
                                testId="transactional-log-filter-button"
                                fields={[
                                    {
                                        key: 'status',
                                        icon: StatusIcon,
                                        label: 'Status',
                                        value: filters.status,
                                        allLabel: 'All statuses',
                                        options: Object.entries(
                                            DELIVERY_STATUS_LABELS,
                                        ).map(([value, label]) => ({
                                            value,
                                            label,
                                        })),
                                        onValueChange: (status) =>
                                            visit({ status }),
                                        testId: 'transactional-log-status-filter',
                                    },
                                ]}
                            />
                            <ListSearch
                                key={searchKey}
                                value={filters.q}
                                onSearch={(q) => visit({ q })}
                                placeholder="Search by recipient"
                            />
                        </div>
                        <ActiveFilters
                            filters={[
                                ...(filters.q !== ''
                                    ? [
                                          {
                                              key: 'q',
                                              field: 'Search',
                                              value: filters.q,
                                              onClear: () => clear({ q: '' }),
                                          },
                                      ]
                                    : []),
                                ...(filters.status !== ''
                                    ? [
                                          {
                                              key: 'status',
                                              field: 'Status',
                                              value:
                                                  DELIVERY_STATUS_LABELS[
                                                      filters.status as EmailDeliveryStatus
                                                  ] ?? filters.status,
                                              onClear: () =>
                                                  clear({ status: '' }),
                                          },
                                      ]
                                    : []),
                            ]}
                            onClearAll={clearAll}
                            clearTestId="clear-transactional-log-filters"
                        />
                    </div>
                )}

                {deliveries.data.length === 0 ? (
                    <Empty>
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <HugeiconsIcon icon={MailSend02Icon} />
                            </EmptyMedia>
                            <EmptyTitle>
                                {hasActiveFilters
                                    ? 'No matching messages'
                                    : 'Nothing sent yet'}
                            </EmptyTitle>
                            <EmptyDescription>
                                {hasActiveFilters
                                    ? 'Try a different recipient or status.'
                                    : 'Messages appear here once the API or an automation sends this transactional email.'}
                            </EmptyDescription>
                        </EmptyHeader>
                    </Empty>
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Recipient</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead className="hidden md:table-cell">
                                    Source
                                </TableHead>
                                <TableHead className="text-right">
                                    Queued
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {deliveries.data.map((delivery) => (
                                <TableRow
                                    key={delivery.uuid}
                                    data-test="transactional-log-row"
                                >
                                    <TableCell>
                                        <div className="flex min-w-0 flex-col">
                                            <span className="truncate font-medium">
                                                {delivery.to}
                                            </span>
                                            <span className="truncate text-muted-foreground">
                                                {delivery.subject}
                                            </span>
                                            {delivery.failure_reason ? (
                                                <span className="text-xs text-destructive">
                                                    {delivery.failure_reason}
                                                </span>
                                            ) : null}
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <Badge
                                            variant={deliveryStatusVariant(
                                                delivery.status,
                                            )}
                                        >
                                            {
                                                DELIVERY_STATUS_LABELS[
                                                    delivery.status
                                                ]
                                            }
                                        </Badge>
                                    </TableCell>
                                    <TableCell className="hidden text-muted-foreground md:table-cell">
                                        {delivery.source
                                            ? `API key ${delivery.source}`
                                            : 'Automation or confirmation'}
                                    </TableCell>
                                    <TableCell className="text-right text-muted-foreground tabular-nums">
                                        {delivery.queued_at
                                            ? `${formatRelativeTime(delivery.queued_at)} ago`
                                            : '—'}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}

                <Paginator paginator={deliveries} />
            </div>
        </>
    );
}

TransactionalLog.layout = (props: {
    currentTeam?: { slug: string } | null;
}) => ({
    breadcrumbs: [
        {
            title: 'Transactional',
            href: props.currentTeam ? index(props.currentTeam.slug) : '/',
        },
    ],
});
