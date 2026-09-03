import {
    CleanIcon,
    ClockFadingIcon,
    Delete02Icon,
    MailRemove01Icon,
    UserGroupIcon,
} from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import {
    destroyInactive,
    destroyUnconfirmed,
} from '@/actions/App/Http/Controllers/ListHygieneController';
import { ActiveFilters } from '@/components/active-filters';
import { FilterMenu } from '@/components/filter-menu';
import Heading from '@/components/heading';
import { ListSearch } from '@/components/list-search';
import { Paginator } from '@/components/paginator';
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
import { Checkbox } from '@/components/ui/checkbox';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
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
import { useInitials } from '@/hooks/use-initials';
import { useListFilters } from '@/hooks/use-list-filters';
import { formatRelativeTime } from '@/lib/format';
import { show as showAudience } from '@/routes/audiences';
import { show as showSubscriber } from '@/routes/audiences/subscribers';
import { index } from '@/routes/list_hygiene';
import type { Paginated } from '@/types/audiences';

type HygieneKind = 'unconfirmed' | 'inactive';

type AudienceHygieneSummary = {
    uuid: string;
    name: string;
    avatar: string;
    subscribers_count: number;
    unconfirmed_count: number;
    inactive_count: number;
};

type HygieneSubscriber = {
    uuid: string;
    avatar: string;
    email: string;
    first_name: string | null;
    last_name: string | null;
    audience: Pick<AudienceHygieneSummary, 'uuid' | 'name' | 'avatar'>;
    created_at: string | null;
    last_sent_at: string | null;
};

type HygieneFilters = {
    kind: HygieneKind;
    audience: string;
    search: string;
};

type Props = {
    audiences: AudienceHygieneSummary[];
    subscribers: Paginated<HygieneSubscriber>;
    filters: HygieneFilters;
    canManage: boolean;
};

const hygieneCopy: Record<
    HygieneKind,
    {
        label: string;
        description: string;
        emptyTitle: string;
        emptyDescription: string;
        icon: typeof MailRemove01Icon;
    }
> = {
    unconfirmed: {
        label: 'Unconfirmed',
        description:
            'Subscribers who joined a double opt-in audience but did not confirm their subscription.',
        emptyTitle: 'No unconfirmed subscribers',
        emptyDescription:
            'Everyone in the selected audience has confirmed, or no one is waiting for confirmation.',
        icon: MailRemove01Icon,
    },
    inactive: {
        label: 'Inactive',
        description:
            'Subscribers who received a campaign but have never opened or clicked one.',
        emptyTitle: 'No inactive subscribers',
        emptyDescription:
            'No subscribers in the selected audience match the inactivity rule.',
        icon: ClockFadingIcon,
    },
};

export default function ListHygieneIndex({
    audiences,
    subscribers,
    filters,
    canManage,
}: Props) {
    const { currentTeam } = usePage().props;
    const getInitials = useInitials();
    const [selected, setSelected] = useState<Set<string>>(new Set());
    const [confirmationOpen, setConfirmationOpen] = useState(false);
    const [processing, setProcessing] = useState(false);
    const { visit, clear, clearAll, hasActiveFilters, searchKey } =
        useListFilters<HygieneFilters>({
            url: currentTeam ? index.url(currentTeam.slug) : '',
            filters,
            empty: { audience: 'all', search: '' },
            searchField: 'search',
        });

    if (!currentTeam) {
        return null;
    }

    const counts = audiences.reduce(
        (totals, audience) => ({
            unconfirmed: totals.unconfirmed + audience.unconfirmed_count,
            inactive: totals.inactive + audience.inactive_count,
        }),
        { unconfirmed: 0, inactive: 0 },
    );
    const audienceLabel =
        audiences.find((audience) => audience.uuid === filters.audience)
            ?.name ?? filters.audience;
    const pageIds = subscribers.data.map((subscriber) => subscriber.uuid);
    const allSelected =
        pageIds.length > 0 && pageIds.every((id) => selected.has(id));
    const someSelected = pageIds.some((id) => selected.has(id));
    const copy = hygieneCopy[filters.kind];

    const updateFilters = (next: Partial<HygieneFilters>) => {
        setSelected(new Set());
        visit(next);
    };

    const removeSelected = () => {
        const action =
            filters.kind === 'unconfirmed'
                ? destroyUnconfirmed(currentTeam.slug)
                : destroyInactive(currentTeam.slug);

        router.delete(action.url, {
            data: { subscribers: [...selected] },
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onSuccess: () => {
                setSelected(new Set());
                setConfirmationOpen(false);
            },
            onFinish: () => setProcessing(false),
        });
    };

    return (
        <>
            <Head title={`List hygiene · ${currentTeam.name}`} />

            <div className="flex flex-col gap-8">
                <Heading
                    title="List hygiene"
                    description="Review and clean hygiene candidates across every audience in this workspace."
                />

                <Tabs
                    value={filters.kind}
                    onValueChange={(kind) =>
                        updateFilters({ kind: kind as HygieneKind })
                    }
                >
                    <TabsList variant="sliding">
                        <TabsTrigger value="unconfirmed">
                            Unconfirmed
                            <Badge variant="secondary">
                                {counts.unconfirmed.toLocaleString()}
                            </Badge>
                        </TabsTrigger>
                        <TabsTrigger value="inactive">
                            Inactive
                            <Badge variant="secondary">
                                {counts.inactive.toLocaleString()}
                            </Badge>
                        </TabsTrigger>
                    </TabsList>
                </Tabs>

                <div className="flex flex-col gap-5">
                    <Heading
                        title={`${copy.label} subscribers`}
                        description={copy.description}
                        variant="small"
                    />
                    <div className="flex flex-col gap-5">
                        <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <FilterMenu
                                testId="hygiene-filter-button"
                                fields={[
                                    {
                                        key: 'audience',
                                        icon: UserGroupIcon,
                                        label: 'Audience',
                                        value: filters.audience,
                                        allValue: 'all',
                                        allLabel: 'All audiences',
                                        options: audiences.map((audience) => ({
                                            value: audience.uuid,
                                            label: audience.name,
                                        })),
                                        onValueChange: (audience) =>
                                            updateFilters({ audience }),
                                        testId: 'hygiene-audience-filter',
                                    },
                                ]}
                            />
                            <ListSearch
                                key={searchKey}
                                value={filters.search}
                                onSearch={(search) => updateFilters({ search })}
                                placeholder="Search name or email"
                                label="Search hygiene subscribers"
                                className="sm:min-w-72"
                            />
                        </div>

                        <ActiveFilters
                            filters={[
                                ...(filters.search !== ''
                                    ? [
                                          {
                                              key: 'search',
                                              field: 'Search',
                                              value: filters.search,
                                              onClear: () => {
                                                  setSelected(new Set());
                                                  clear({ search: '' });
                                              },
                                          },
                                      ]
                                    : []),
                                ...(filters.audience !== 'all'
                                    ? [
                                          {
                                              key: 'audience',
                                              field: 'Audience',
                                              value: audienceLabel,
                                              onClear: () => {
                                                  setSelected(new Set());
                                                  clear({ audience: 'all' });
                                              },
                                          },
                                      ]
                                    : []),
                            ]}
                            onClearAll={() => {
                                setSelected(new Set());
                                clearAll();
                            }}
                            clearTestId="clear-hygiene-filters"
                        />

                        {canManage && selected.size > 0 && (
                            <div className="flex flex-wrap items-center justify-between gap-3 rounded-lg bg-muted/50 px-4 py-3">
                                <p className="text-sm text-muted-foreground">
                                    {selected.size.toLocaleString()}{' '}
                                    {selected.size === 1
                                        ? 'subscriber'
                                        : 'subscribers'}{' '}
                                    selected
                                </p>
                                <Button
                                    type="button"
                                    variant="destructive"
                                    size="sm"
                                    onClick={() => setConfirmationOpen(true)}
                                >
                                    <HugeiconsIcon
                                        icon={Delete02Icon}
                                        data-icon="inline-start"
                                    />
                                    Remove selected
                                </Button>
                            </div>
                        )}

                        {audiences.length === 0 ? (
                            <Empty>
                                <EmptyHeader>
                                    <EmptyMedia variant="icon">
                                        <HugeiconsIcon icon={CleanIcon} />
                                    </EmptyMedia>
                                    <EmptyTitle>No audiences yet</EmptyTitle>
                                    <EmptyDescription>
                                        Create an audience first. Its hygiene
                                        candidates will appear here.
                                    </EmptyDescription>
                                </EmptyHeader>
                            </Empty>
                        ) : subscribers.data.length === 0 ? (
                            <Empty>
                                <EmptyHeader>
                                    <EmptyMedia variant="icon">
                                        <HugeiconsIcon icon={copy.icon} />
                                    </EmptyMedia>
                                    <EmptyTitle>{copy.emptyTitle}</EmptyTitle>
                                    <EmptyDescription>
                                        {hasActiveFilters
                                            ? 'Try adjusting or clearing the current filters.'
                                            : copy.emptyDescription}
                                    </EmptyDescription>
                                </EmptyHeader>
                            </Empty>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        {canManage && (
                                            <TableHead className="w-10">
                                                <Checkbox
                                                    aria-label="Select all subscribers on this page"
                                                    data-test="hygiene-select-all"
                                                    checked={allSelected}
                                                    indeterminate={
                                                        someSelected &&
                                                        !allSelected
                                                    }
                                                    onCheckedChange={(
                                                        checked,
                                                    ) => {
                                                        const next = new Set(
                                                            selected,
                                                        );

                                                        if (checked === true) {
                                                            pageIds.forEach(
                                                                (id) =>
                                                                    next.add(
                                                                        id,
                                                                    ),
                                                            );
                                                        } else {
                                                            pageIds.forEach(
                                                                (id) =>
                                                                    next.delete(
                                                                        id,
                                                                    ),
                                                            );
                                                        }

                                                        setSelected(next);
                                                    }}
                                                />
                                            </TableHead>
                                        )}
                                        <TableHead>Subscriber</TableHead>
                                        <TableHead>Audience</TableHead>
                                        <TableHead>
                                            {filters.kind === 'unconfirmed'
                                                ? 'Signed up'
                                                : 'Last campaign'}
                                        </TableHead>
                                        <TableHead>Reason</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {subscribers.data.map((subscriber) => {
                                        const fullName = [
                                            subscriber.first_name,
                                            subscriber.last_name,
                                        ]
                                            .filter(Boolean)
                                            .join(' ');
                                        const activityAt =
                                            filters.kind === 'unconfirmed'
                                                ? subscriber.created_at
                                                : subscriber.last_sent_at;

                                        return (
                                            <TableRow
                                                key={subscriber.uuid}
                                                data-test="hygiene-subscriber-row"
                                                data-state={
                                                    selected.has(
                                                        subscriber.uuid,
                                                    )
                                                        ? 'selected'
                                                        : undefined
                                                }
                                            >
                                                {canManage && (
                                                    <TableCell>
                                                        <Checkbox
                                                            aria-label={`Select ${subscriber.email}`}
                                                            data-test="hygiene-row-select"
                                                            checked={selected.has(
                                                                subscriber.uuid,
                                                            )}
                                                            onCheckedChange={(
                                                                checked,
                                                            ) => {
                                                                const next =
                                                                    new Set(
                                                                        selected,
                                                                    );

                                                                if (
                                                                    checked ===
                                                                    true
                                                                ) {
                                                                    next.add(
                                                                        subscriber.uuid,
                                                                    );
                                                                } else {
                                                                    next.delete(
                                                                        subscriber.uuid,
                                                                    );
                                                                }

                                                                setSelected(
                                                                    next,
                                                                );
                                                            }}
                                                        />
                                                    </TableCell>
                                                )}
                                                <TableCell className="max-w-0">
                                                    <Link
                                                        href={showSubscriber([
                                                            currentTeam.slug,
                                                            subscriber.audience
                                                                .uuid,
                                                            subscriber.uuid,
                                                        ])}
                                                        className="flex min-w-56 items-center gap-3 rounded-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                                        prefetch
                                                    >
                                                        <Avatar size="sm">
                                                            <AvatarImage
                                                                src={
                                                                    subscriber.avatar
                                                                }
                                                                alt=""
                                                            />
                                                            <AvatarFallback>
                                                                {getInitials(
                                                                    fullName ||
                                                                        subscriber.email,
                                                                )}
                                                            </AvatarFallback>
                                                        </Avatar>
                                                        <span className="min-w-0">
                                                            <span className="block truncate font-medium hover:underline">
                                                                {fullName ||
                                                                    subscriber.email}
                                                            </span>
                                                            {fullName && (
                                                                <span className="block truncate text-sm text-muted-foreground">
                                                                    {
                                                                        subscriber.email
                                                                    }
                                                                </span>
                                                            )}
                                                        </span>
                                                    </Link>
                                                </TableCell>
                                                <TableCell className="max-w-0">
                                                    <Link
                                                        href={showAudience([
                                                            currentTeam.slug,
                                                            subscriber.audience
                                                                .uuid,
                                                        ])}
                                                        className="flex min-w-40 items-center gap-2 rounded-sm text-muted-foreground outline-none hover:text-foreground hover:underline focus-visible:ring-2 focus-visible:ring-ring"
                                                        prefetch
                                                    >
                                                        <Avatar size="sm">
                                                            <AvatarImage
                                                                src={
                                                                    subscriber
                                                                        .audience
                                                                        .avatar
                                                                }
                                                                alt=""
                                                            />
                                                            <AvatarFallback>
                                                                {getInitials(
                                                                    subscriber
                                                                        .audience
                                                                        .name,
                                                                )}
                                                            </AvatarFallback>
                                                        </Avatar>
                                                        <span className="truncate">
                                                            {
                                                                subscriber
                                                                    .audience
                                                                    .name
                                                            }
                                                        </span>
                                                    </Link>
                                                </TableCell>
                                                <TableCell
                                                    className="text-muted-foreground"
                                                    title={
                                                        activityAt
                                                            ? new Date(
                                                                  activityAt,
                                                              ).toLocaleString()
                                                            : undefined
                                                    }
                                                >
                                                    {activityAt
                                                        ? formatRelativeTime(
                                                              activityAt,
                                                          )
                                                        : '—'}
                                                </TableCell>
                                                <TableCell>
                                                    <Badge
                                                        variant={
                                                            filters.kind ===
                                                            'unconfirmed'
                                                                ? 'secondary'
                                                                : 'orange'
                                                        }
                                                    >
                                                        {filters.kind ===
                                                        'unconfirmed'
                                                            ? 'Awaiting confirmation'
                                                            : 'No opens or clicks'}
                                                    </Badge>
                                                </TableCell>
                                            </TableRow>
                                        );
                                    })}
                                </TableBody>
                            </Table>
                        )}

                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            {subscribers.total > 0 && (
                                <p className="text-sm text-muted-foreground">
                                    Showing {subscribers.from}–{subscribers.to}{' '}
                                    of {subscribers.total.toLocaleString()}{' '}
                                    subscribers
                                </p>
                            )}
                            <Paginator paginator={subscribers} />
                        </div>
                    </div>
                </div>

                <AlertDialog
                    open={confirmationOpen}
                    onOpenChange={(open) => {
                        if (!open && !processing) {
                            setConfirmationOpen(false);
                        }
                    }}
                >
                    <AlertDialogContent>
                        <AlertDialogHeader>
                            <AlertDialogTitle>
                                Remove selected {filters.kind} subscribers?
                            </AlertDialogTitle>
                            <AlertDialogDescription>
                                {selected.size.toLocaleString()}{' '}
                                {selected.size === 1
                                    ? 'subscriber'
                                    : 'subscribers'}{' '}
                                will be permanently removed. Past campaign
                                delivery records are retained for reporting.
                            </AlertDialogDescription>
                        </AlertDialogHeader>
                        <AlertDialogFooter>
                            <AlertDialogCancel disabled={processing}>
                                Cancel
                            </AlertDialogCancel>
                            <AlertDialogAction
                                variant="destructive"
                                disabled={processing || selected.size === 0}
                                onClick={removeSelected}
                            >
                                {processing && (
                                    <Spinner data-icon="inline-start" />
                                )}
                                Remove permanently
                            </AlertDialogAction>
                        </AlertDialogFooter>
                    </AlertDialogContent>
                </AlertDialog>
            </div>
        </>
    );
}

ListHygieneIndex.layout = (props: {
    currentTeam?: { slug: string } | null;
}) => ({
    breadcrumbs: [
        {
            title: 'List hygiene',
            href: props.currentTeam ? index(props.currentTeam.slug) : '/',
        },
    ],
});
