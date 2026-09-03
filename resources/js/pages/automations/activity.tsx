import {
    ArrowDown01Icon,
    ArrowRight01Icon,
    Edit03Icon,
    NodeEditIcon,
    StatusIcon,
} from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { Head, Link, usePage } from '@inertiajs/react';
import { Fragment, useState } from 'react';
import { ActiveFilters } from '@/components/active-filters';
import { FilterMenu } from '@/components/filter-menu';
import { ListSearch } from '@/components/list-search';
import { Paginator } from '@/components/paginator';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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
import { formatRelativeTime, formatTimeUntil } from '@/lib/format';
import { show as showSubscriber } from '@/routes/audiences/subscribers';
import { activity, edit, index } from '@/routes/automations';
import type {
    AutomationActivityFilters,
    AutomationActivitySummary,
    AutomationCatalogOption,
    AutomationRunRow,
    AutomationRunStatus,
    AutomationStatus,
} from '@/types';
import type { Paginated } from '@/types/audiences';

type Props = {
    automation: {
        uuid: string;
        name: string;
        status: AutomationStatus;
        trigger_label: string;
    };
    runs: Paginated<AutomationRunRow>;
    filters: AutomationActivityFilters;
    summary: AutomationActivitySummary;
    statuses: AutomationCatalogOption[];
    canManage: boolean;
};

const STATUS_VARIANTS: Record<
    AutomationRunStatus,
    'success' | 'secondary' | 'amber' | 'destructive'
> = {
    pending: 'secondary',
    running: 'secondary',
    waiting: 'amber',
    completed: 'success',
    failed: 'destructive',
    cancelled: 'secondary',
};

const STEP_VARIANTS: Record<string, 'success' | 'secondary' | 'destructive'> = {
    completed: 'success',
    skipped: 'secondary',
    waiting: 'secondary',
    sending: 'secondary',
    failed: 'destructive',
};

export default function AutomationActivity({
    automation,
    runs,
    filters,
    summary,
    statuses,
    canManage,
}: Props) {
    const { currentTeam } = usePage().props;
    const [expanded, setExpanded] = useState<string[]>([]);
    const { visit, clear, clearAll, hasActiveFilters, searchKey } =
        useListFilters<AutomationActivityFilters>({
            url: currentTeam
                ? activity.url([currentTeam.slug, automation.uuid])
                : '',
            filters,
            empty: { q: '', status: '' },
        });

    if (!currentTeam) {
        return null;
    }

    const toggle = (uuid: string) =>
        setExpanded((open) =>
            open.includes(uuid)
                ? open.filter((value) => value !== uuid)
                : [...open, uuid],
        );

    return (
        <>
            <Head title={`${automation.name} activity`} />
            <div className="flex flex-1 flex-col gap-6">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                    <div className="flex flex-col gap-1">
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {automation.name}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Every subscriber this automation enrolled, and the
                            steps they walked. Trigger:{' '}
                            {automation.trigger_label.toLowerCase()}.
                        </p>
                    </div>
                    <Button
                        variant="outline"
                        render={
                            <Link
                                href={edit([currentTeam.slug, automation.uuid])}
                                prefetch
                            />
                        }
                    >
                        <HugeiconsIcon
                            icon={Edit03Icon}
                            data-icon="inline-start"
                        />
                        {canManage ? 'Edit flow' : 'View flow'}
                    </Button>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <MetricCard
                        label="Enrolled"
                        value={summary.enrolled.toLocaleString()}
                        detail="Subscribers who ever entered"
                    />
                    <MetricCard
                        label="In flight"
                        value={summary.in_flight.toLocaleString()}
                        detail="Running or waiting on a delay"
                    />
                    <MetricCard
                        label="Completed"
                        value={summary.completed.toLocaleString()}
                        detail="Reached the end of the flow"
                    />
                    <MetricCard
                        label="Failed"
                        value={summary.failed.toLocaleString()}
                        detail="Stopped on an error"
                    />
                </div>

                {(runs.data.length > 0 || hasActiveFilters) && (
                    <div className="flex flex-col gap-3">
                        <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <FilterMenu
                                testId="run-filter-button"
                                fields={[
                                    {
                                        key: 'status',
                                        icon: StatusIcon,
                                        label: 'Status',
                                        value: filters.status,
                                        allLabel: 'All statuses',
                                        options: statuses,
                                        onValueChange: (status) =>
                                            visit({ status }),
                                        testId: 'run-status-filter',
                                    },
                                ]}
                            />
                            <ListSearch
                                key={searchKey}
                                value={filters.q}
                                onSearch={(q) => visit({ q })}
                                placeholder="Search by email address"
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
                                                  statuses.find(
                                                      (status) =>
                                                          status.value ===
                                                          filters.status,
                                                  )?.label ?? filters.status,
                                              onClear: () =>
                                                  clear({ status: '' }),
                                          },
                                      ]
                                    : []),
                            ]}
                            onClearAll={clearAll}
                            clearTestId="clear-run-filters"
                        />
                    </div>
                )}

                {runs.data.length === 0 ? (
                    <Empty>
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <HugeiconsIcon icon={NodeEditIcon} />
                            </EmptyMedia>
                            <EmptyTitle>
                                {hasActiveFilters
                                    ? 'No runs match these filters'
                                    : 'No runs yet'}
                            </EmptyTitle>
                            <EmptyDescription>
                                {hasActiveFilters
                                    ? 'Clear the filters to see every run this automation recorded.'
                                    : 'Once this automation is active and someone matches its trigger, their run appears here.'}
                            </EmptyDescription>
                        </EmptyHeader>
                    </Empty>
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead className="w-10" />
                                <TableHead>Subscriber</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Current step</TableHead>
                                <TableHead>Started</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {runs.data.map((run) => {
                                const isOpen = expanded.includes(run.uuid);

                                return (
                                    <Fragment key={run.uuid}>
                                        <TableRow data-test="automation-run-row">
                                            <TableCell>
                                                <Button
                                                    size="icon"
                                                    variant="ghost"
                                                    data-test="toggle-run-steps"
                                                    aria-expanded={isOpen}
                                                    aria-label={`${isOpen ? 'Hide' : 'Show'} steps for ${run.subscriber?.email ?? 'this run'}`}
                                                    onClick={() =>
                                                        toggle(run.uuid)
                                                    }
                                                >
                                                    <HugeiconsIcon
                                                        icon={
                                                            isOpen
                                                                ? ArrowDown01Icon
                                                                : ArrowRight01Icon
                                                        }
                                                    />
                                                </Button>
                                            </TableCell>
                                            <TableCell>
                                                {run.subscriber ? (
                                                    <div className="flex min-w-0 flex-col">
                                                        <Link
                                                            href={showSubscriber.url(
                                                                [
                                                                    currentTeam.slug,
                                                                    run
                                                                        .subscriber
                                                                        .audience_uuid ??
                                                                        '',
                                                                    run
                                                                        .subscriber
                                                                        .uuid,
                                                                ],
                                                            )}
                                                            className="truncate font-medium underline-offset-4 hover:underline"
                                                        >
                                                            {run.subscriber
                                                                .name ??
                                                                run.subscriber
                                                                    .email}
                                                        </Link>
                                                        {run.subscriber
                                                            .name && (
                                                            <span className="truncate text-muted-foreground">
                                                                {
                                                                    run
                                                                        .subscriber
                                                                        .email
                                                                }
                                                            </span>
                                                        )}
                                                    </div>
                                                ) : (
                                                    <span className="text-muted-foreground">
                                                        Deleted subscriber
                                                    </span>
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex flex-col items-start gap-1">
                                                    <Badge
                                                        data-test="run-status"
                                                        variant={
                                                            STATUS_VARIANTS[
                                                                run.status
                                                            ]
                                                        }
                                                    >
                                                        {run.status_label}
                                                    </Badge>
                                                    {run.failure_reason && (
                                                        <span className="text-xs text-destructive">
                                                            {run.failure_reason}
                                                        </span>
                                                    )}
                                                </div>
                                            </TableCell>
                                            <TableCell className="text-muted-foreground">
                                                {run.current_step ?? '—'}
                                                {run.scheduled_at && (
                                                    <span className="block text-xs">
                                                        Resumes{' '}
                                                        {formatTimeUntil(
                                                            run.scheduled_at,
                                                        )}
                                                    </span>
                                                )}
                                            </TableCell>
                                            <TableCell className="text-muted-foreground">
                                                {run.started_at
                                                    ? formatRelativeTime(
                                                          run.started_at,
                                                      )
                                                    : '—'}
                                            </TableCell>
                                        </TableRow>
                                        {isOpen && (
                                            <TableRow data-test="automation-run-steps">
                                                <TableCell colSpan={5}>
                                                    <StepTimeline
                                                        steps={run.steps}
                                                    />
                                                </TableCell>
                                            </TableRow>
                                        )}
                                    </Fragment>
                                );
                            })}
                        </TableBody>
                    </Table>
                )}

                <Paginator paginator={runs} />
            </div>
        </>
    );
}

function StepTimeline({ steps }: { steps: AutomationRunRow['steps'] }) {
    if (steps.length === 0) {
        return (
            <p className="py-2 text-sm text-muted-foreground">
                This run has not recorded a step yet.
            </p>
        );
    }

    return (
        <ol className="flex flex-col gap-3 py-2">
            {steps.map((step) => (
                <li
                    key={step.uuid}
                    className="flex flex-col gap-1 border-l-2 pl-4 sm:flex-row sm:items-center sm:justify-between sm:gap-4"
                    data-test="automation-run-step"
                >
                    <div className="flex min-w-0 flex-col">
                        <span className="font-medium">{step.label}</span>
                        {step.detail && (
                            <span className="text-muted-foreground">
                                {step.detail}
                            </span>
                        )}
                    </div>
                    <div className="flex shrink-0 items-center gap-3">
                        <Badge
                            variant={STEP_VARIANTS[step.status] ?? 'secondary'}
                        >
                            {step.status}
                        </Badge>
                        <span className="text-muted-foreground">
                            {step.processed_at
                                ? formatRelativeTime(step.processed_at)
                                : '—'}
                        </span>
                    </div>
                </li>
            ))}
        </ol>
    );
}

function MetricCard({
    label,
    value,
    detail,
}: {
    label: string;
    value: string;
    detail: string;
}) {
    return (
        <Card>
            <CardHeader>
                <CardDescription>{label}</CardDescription>
                <CardTitle className="text-3xl tabular-nums">{value}</CardTitle>
            </CardHeader>
            <CardContent>
                <p className="text-sm text-muted-foreground">{detail}</p>
            </CardContent>
        </Card>
    );
}

AutomationActivity.layout = (props: {
    currentTeam?: { slug: string } | null;
    automation?: { uuid: string; name: string };
}) => ({
    breadcrumbs: [
        {
            title: 'Automations',
            href: props.currentTeam ? index(props.currentTeam.slug) : '/',
        },
        ...(props.currentTeam && props.automation
            ? [
                  {
                      title: props.automation.name,
                      href: activity([
                          props.currentTeam.slug,
                          props.automation.uuid,
                      ]),
                  },
              ]
            : []),
    ],
});
