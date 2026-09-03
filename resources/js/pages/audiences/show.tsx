import {
    Add01Icon,
    ArrowRight01Icon,
    Delete02Icon,
    Edit03Icon,
    Mail01Icon,
    MailAtSign02Icon,
    MoreHorizontalIcon,
    StatusIcon,
    UserCheck01Icon,
    UserGroupIcon,
    UserIcon,
    UserRemove01Icon,
} from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { Form, Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { ActiveFilters } from '@/components/active-filters';
import { ContactDialog } from '@/components/contact-dialog';
import { ContactImportDialog } from '@/components/contact-import-dialog';
import { FilterMenu } from '@/components/filter-menu';
import { ListSearch } from '@/components/list-search';
import { Paginator } from '@/components/paginator';
import { SegmentRuleFields } from '@/components/segment-rule-fields';
import { SubscriberDialog } from '@/components/subscriber-dialog';
import {
    SubscriberHoverCard,
    subscriberSourceIcon,
    subscriberSourceLabel,
} from '@/components/subscriber-hover-card';
import { SubscriberStatsChart } from '@/components/subscriber-stats-chart';
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
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
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
    FieldError,
    FieldGroup,
    FieldLabel,
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
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useListFilters } from '@/hooks/use-list-filters';
import { formatRelativeTime } from '@/lib/format';
import { tagBadgeVariant } from '@/lib/tags';
import { edit, show } from '@/routes/audiences';
import { show as showAudienceExport } from '@/routes/audiences/exports';
import { store as storeContactImport } from '@/routes/audiences/imports';
import {
    destroy as destroySegment,
    show as showSegment,
    store as storeSegment,
    update as updateSegment,
} from '@/routes/audiences/segments';
import {
    edit as editSubscribeForm,
    store as storeSubscribeForm,
} from '@/routes/audiences/subscribe_forms';
import {
    bulkDestroy,
    bulkUnsubscribe,
    destroy as destroySubscriber,
    resubscribe,
    show as showSubscriber,
    unsubscribe,
} from '@/routes/audiences/subscribers';
import type {
    Audience,
    AudienceSubscriberStats,
    Paginated,
    Segment,
    SegmentRule,
    Subscriber,
    SubscribeFormSummary,
    SubscriberIndexFilters,
    Tag,
} from '@/types/audiences';
import type { CompanyOption } from '@/types/contacts';
import type { ContactImport } from '@/types/contacts';

const MAX_VISIBLE_TAGS = 3;

type Props = {
    audience: Audience;
    subscribers: Paginated<Subscriber>;
    subscriberStats: AudienceSubscriberStats;
    segments: Segment[];
    forms: SubscribeFormSummary[];
    companies: CompanyOption[];
    tags: Tag[];
    contactImports: ContactImport[];
    filters: SubscriberIndexFilters;
    canManage: boolean;
};

const SOURCE_FILTER_OPTIONS = [
    { value: 'manual', label: 'Manual' },
    { value: 'form', label: 'Subscribe form' },
    { value: 'api', label: 'API' },
];

const STATUS_FILTER_LABELS: Record<string, string> = {
    subscribed: 'Subscribed',
    unsubscribed: 'Unsubscribed',
};

const SOURCE_FILTER_LABELS: Record<string, string> = Object.fromEntries(
    SOURCE_FILTER_OPTIONS.map((option) => [option.value, option.label]),
);

export default function AudienceShow(props: Props) {
    const {
        audience,
        subscribers,
        subscriberStats,
        segments,
        forms,
        companies,
        tags,
        contactImports,
        filters,
        canManage,
    } = props;
    const { currentTeam } = usePage().props;
    const [subscriberOpen, setSubscriberOpen] = useState(false);
    const [segmentOpen, setSegmentOpen] = useState(false);
    const [segmentToEdit, setSegmentToEdit] = useState<Segment | null>(null);
    const [segmentToDelete, setSegmentToDelete] = useState<Segment | null>(
        null,
    );
    const [formOpen, setFormOpen] = useState(false);
    const [selected, setSelected] = useState<Set<string>>(new Set());
    const {
        visit: visitSubscribers,
        clear: clearSubscriberFilter,
        clearAll: clearSubscriberFilters,
        searchKey,
    } = useListFilters<SubscriberIndexFilters>({
        url: currentTeam ? show.url([currentTeam.slug, audience.uuid]) : '',
        filters,
        empty: { search: '', status: 'all', source: 'all' },
        searchField: 'search',
    });

    if (!currentTeam) {
        return null;
    }

    const routeArgs: [string, string] = [currentTeam.slug, audience.uuid];

    return (
        <>
            <Head title={audience.name} />
            <div className="flex flex-1 flex-col gap-6">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                    <div className="flex flex-col gap-1">
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {audience.name}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {audience.description ||
                                'Manage contacts, segments, and forms.'}
                        </p>
                    </div>
                    {canManage && (
                        <Button
                            variant="outline"
                            nativeButton={false}
                            render={<Link href={edit(routeArgs)} prefetch />}
                        >
                            Audience settings
                        </Button>
                    )}
                </div>

                <Tabs defaultValue="subscribers">
                    <TabsList variant="sliding">
                        <TabsTrigger value="subscribers">Contacts</TabsTrigger>
                        <TabsTrigger value="segments">Segments</TabsTrigger>
                        <TabsTrigger value="forms">Subscribe forms</TabsTrigger>
                    </TabsList>

                    <TabsContent
                        value="subscribers"
                        className="flex flex-col gap-6"
                    >
                        <SubscriberStatsChart stats={subscriberStats} />

                        <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                            <FilterMenu
                                testId="subscriber-filter-button"
                                fields={[
                                    {
                                        key: 'status',
                                        icon: StatusIcon,
                                        label: 'Status',
                                        value: filters.status,
                                        allValue: 'all',
                                        allLabel: 'All statuses',
                                        options: [
                                            {
                                                value: 'subscribed',
                                                label: 'Subscribed',
                                            },
                                            {
                                                value: 'unsubscribed',
                                                label: 'Unsubscribed',
                                            },
                                        ],
                                        onValueChange: (status) =>
                                            visitSubscribers({ status }),
                                        testId: 'subscriber-status-filter',
                                    },
                                    {
                                        key: 'source',
                                        icon: Mail01Icon,
                                        label: 'Source',
                                        value: filters.source,
                                        allValue: 'all',
                                        allLabel: 'All sources',
                                        options: SOURCE_FILTER_OPTIONS,
                                        onValueChange: (source) =>
                                            visitSubscribers({ source }),
                                        testId: 'subscriber-source-filter',
                                    },
                                ]}
                            />
                            <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
                                <ListSearch
                                    key={searchKey}
                                    value={filters.search}
                                    onSearch={(search) =>
                                        visitSubscribers({ search })
                                    }
                                    placeholder="Search name or email"
                                    label="Search contacts"
                                    className="sm:min-w-64"
                                />
                                {canManage && (
                                    <>
                                        <ContactImportDialog
                                            action={storeContactImport.url(
                                                routeArgs,
                                            )}
                                            imports={contactImports}
                                            audienceName={audience.name}
                                            pollOnly={[
                                                'contactImports',
                                                'subscribers',
                                                'subscriberStats',
                                            ]}
                                            exportCsvUrl={showAudienceExport.url(
                                                [...routeArgs, 'csv'],
                                                { query: filters },
                                            )}
                                            exportXlsUrl={showAudienceExport.url(
                                                [...routeArgs, 'xls'],
                                                { query: filters },
                                            )}
                                        />
                                        <ContactDialog
                                            open={subscriberOpen}
                                            onOpenChange={setSubscriberOpen}
                                            teamSlug={currentTeam.slug}
                                            companies={companies}
                                            tags={tags}
                                            audiences={[
                                                {
                                                    uuid: audience.uuid,
                                                    name: audience.name,
                                                },
                                            ]}
                                            defaultAudience={{
                                                uuid: audience.uuid,
                                                name: audience.name,
                                            }}
                                        />
                                    </>
                                )}
                            </div>
                        </div>
                        <ActiveFilters
                            filters={[
                                ...(filters.search !== ''
                                    ? [
                                          {
                                              key: 'search',
                                              field: 'Search',
                                              value: filters.search,
                                              onClear: () =>
                                                  clearSubscriberFilter({
                                                      search: '',
                                                  }),
                                          },
                                      ]
                                    : []),
                                ...(filters.status !== 'all'
                                    ? [
                                          {
                                              key: 'status',
                                              field: 'Status',
                                              value:
                                                  STATUS_FILTER_LABELS[
                                                      filters.status
                                                  ] ?? filters.status,
                                              onClear: () =>
                                                  clearSubscriberFilter({
                                                      status: 'all',
                                                  }),
                                          },
                                      ]
                                    : []),
                                ...(filters.source !== 'all'
                                    ? [
                                          {
                                              key: 'source',
                                              field: 'Source',
                                              value:
                                                  SOURCE_FILTER_LABELS[
                                                      filters.source
                                                  ] ?? filters.source,
                                              onClear: () =>
                                                  clearSubscriberFilter({
                                                      source: 'all',
                                                  }),
                                          },
                                      ]
                                    : []),
                            ]}
                            onClearAll={clearSubscriberFilters}
                            clearTestId="clear-subscriber-filters"
                        />
                        {canManage && selected.size > 0 && (
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <p className="text-sm text-muted-foreground">
                                    {selected.size} selected
                                </p>
                                <div className="flex gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => {
                                            router.patch(
                                                bulkUnsubscribe.url(routeArgs),
                                                {
                                                    ids: [...selected],
                                                },
                                                {
                                                    preserveScroll: true,
                                                    onSuccess: () =>
                                                        setSelected(new Set()),
                                                },
                                            );
                                        }}
                                    >
                                        Unsubscribe
                                    </Button>
                                    <Button
                                        variant="destructive"
                                        size="sm"
                                        onClick={() => {
                                            router.delete(
                                                bulkDestroy.url(routeArgs),
                                                {
                                                    data: {
                                                        ids: [...selected],
                                                    },
                                                    preserveScroll: true,
                                                    onSuccess: () =>
                                                        setSelected(new Set()),
                                                },
                                            );
                                        }}
                                    >
                                        Delete
                                    </Button>
                                </div>
                            </div>
                        )}
                        {subscribers.data.length === 0 ? (
                            <Empty>
                                <EmptyHeader>
                                    <EmptyMedia variant="icon">
                                        <HugeiconsIcon icon={UserGroupIcon} />
                                    </EmptyMedia>
                                    <EmptyTitle>No contacts found</EmptyTitle>
                                    <EmptyDescription>
                                        Add a contact or adjust the current
                                        filters.
                                    </EmptyDescription>
                                </EmptyHeader>
                                {canManage && (
                                    <EmptyContent>
                                        <Button
                                            onClick={() =>
                                                setSubscriberOpen(true)
                                            }
                                        >
                                            Add contact
                                        </Button>
                                    </EmptyContent>
                                )}
                            </Empty>
                        ) : (
                            <SubscriberTable
                                subscribers={subscribers.data}
                                routeArgs={routeArgs}
                                canManage={canManage}
                                tags={tags}
                                selected={selected}
                                onSelectedChange={setSelected}
                            />
                        )}
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            {subscribers.total > 0 && (
                                <p className="text-sm text-muted-foreground">
                                    Showing {subscribers.from}–{subscribers.to}{' '}
                                    of {subscribers.total} contacts
                                </p>
                            )}
                            <Paginator paginator={subscribers} />
                        </div>
                    </TabsContent>

                    <TabsContent
                        value="segments"
                        className="flex flex-col gap-6"
                    >
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <p className="text-sm text-muted-foreground">
                                Saved rules update automatically when subscriber
                                data changes.
                            </p>
                            {canManage && (
                                <Button onClick={() => setSegmentOpen(true)}>
                                    <HugeiconsIcon
                                        icon={Add01Icon}
                                        data-icon="inline-start"
                                    />
                                    Add segment
                                </Button>
                            )}
                        </div>
                        {segments.length === 0 ? (
                            <Empty>
                                <EmptyHeader>
                                    <EmptyTitle>No segments yet</EmptyTitle>
                                    <EmptyDescription>
                                        Create rules to identify a useful subset
                                        of this audience.
                                    </EmptyDescription>
                                </EmptyHeader>
                                {canManage && (
                                    <EmptyContent>
                                        <Button
                                            onClick={() => setSegmentOpen(true)}
                                        >
                                            Add segment
                                        </Button>
                                    </EmptyContent>
                                )}
                            </Empty>
                        ) : (
                            <SegmentTable
                                segments={segments}
                                routeArgs={routeArgs}
                                canManage={canManage}
                                onEdit={setSegmentToEdit}
                                onDelete={setSegmentToDelete}
                            />
                        )}
                        <SegmentDialog
                            open={segmentOpen || Boolean(segmentToEdit)}
                            onOpenChange={(open) => {
                                if (!open) {
                                    setSegmentOpen(false);
                                    setSegmentToEdit(null);
                                }
                            }}
                            routeArgs={routeArgs}
                            forms={forms}
                            segment={segmentToEdit}
                        />
                        <AlertDialog
                            open={Boolean(segmentToDelete)}
                            onOpenChange={(open) => {
                                if (!open) {
                                    setSegmentToDelete(null);
                                }
                            }}
                        >
                            <AlertDialogContent>
                                <AlertDialogHeader>
                                    <AlertDialogTitle>
                                        Delete {segmentToDelete?.name}?
                                    </AlertDialogTitle>
                                    <AlertDialogDescription>
                                        Subscribers are not deleted; only the
                                        saved rules are removed.
                                    </AlertDialogDescription>
                                </AlertDialogHeader>
                                <AlertDialogFooter>
                                    <AlertDialogCancel>
                                        Cancel
                                    </AlertDialogCancel>
                                    <AlertDialogAction
                                        variant="destructive"
                                        onClick={() => {
                                            if (!segmentToDelete) {
                                                return;
                                            }

                                            router.delete(
                                                destroySegment.url([
                                                    ...routeArgs,
                                                    segmentToDelete.uuid,
                                                ]),
                                                {
                                                    preserveScroll: true,
                                                    onSuccess: () =>
                                                        setSegmentToDelete(
                                                            null,
                                                        ),
                                                },
                                            );
                                        }}
                                    >
                                        Delete
                                    </AlertDialogAction>
                                </AlertDialogFooter>
                            </AlertDialogContent>
                        </AlertDialog>
                    </TabsContent>

                    <TabsContent value="forms" className="flex flex-col gap-6">
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <p className="text-sm text-muted-foreground">
                                Publish hosted signup pages and embed them on
                                other sites.
                            </p>
                            {canManage && (
                                <Button onClick={() => setFormOpen(true)}>
                                    <HugeiconsIcon
                                        icon={Add01Icon}
                                        data-icon="inline-start"
                                    />
                                    Add form
                                </Button>
                            )}
                        </div>
                        {forms.length === 0 ? (
                            <Empty>
                                <EmptyHeader>
                                    <EmptyMedia variant="icon">
                                        <HugeiconsIcon
                                            icon={MailAtSign02Icon}
                                        />
                                    </EmptyMedia>
                                    <EmptyTitle>No forms yet</EmptyTitle>
                                    <EmptyDescription>
                                        Create a form to collect consented
                                        subscribers.
                                    </EmptyDescription>
                                </EmptyHeader>
                                {canManage && (
                                    <EmptyContent>
                                        <Button
                                            onClick={() => setFormOpen(true)}
                                        >
                                            Add form
                                        </Button>
                                    </EmptyContent>
                                )}
                            </Empty>
                        ) : (
                            <SubscribeFormTable
                                forms={forms}
                                routeArgs={routeArgs}
                                canManage={canManage}
                            />
                        )}
                        {canManage && (
                            <SubscribeFormDialog
                                open={formOpen}
                                onOpenChange={setFormOpen}
                                routeArgs={routeArgs}
                            />
                        )}
                    </TabsContent>
                </Tabs>
            </div>
        </>
    );
}

function SubscriberTable({
    subscribers,
    routeArgs,
    canManage,
    tags,
    selected,
    onSelectedChange,
}: {
    subscribers: Subscriber[];
    routeArgs: [string, string];
    canManage: boolean;
    tags: Tag[];
    selected: Set<string>;
    onSelectedChange: (selected: Set<string>) => void;
}) {
    const pageIds = subscribers.map((subscriber) => subscriber.uuid);
    const allSelected =
        pageIds.length > 0 && pageIds.every((id) => selected.has(id));
    const someSelected = pageIds.some((id) => selected.has(id));

    return (
        <Table>
            <TableHeader>
                <TableRow>
                    {canManage && (
                        <TableHead className="w-10">
                            <Checkbox
                                aria-label="Select all"
                                data-test="subscriber-select-all"
                                checked={allSelected}
                                indeterminate={someSelected && !allSelected}
                                onCheckedChange={(checked) => {
                                    const next = new Set(selected);

                                    if (checked === true) {
                                        pageIds.forEach((id) => next.add(id));
                                    } else {
                                        pageIds.forEach((id) =>
                                            next.delete(id),
                                        );
                                    }

                                    onSelectedChange(next);
                                }}
                            />
                        </TableHead>
                    )}
                    <TableHead>Contact</TableHead>
                    <TableHead>Source</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead>Tags</TableHead>
                    <TableHead>Subscribed</TableHead>
                    {canManage && (
                        <TableHead className="text-right">Actions</TableHead>
                    )}
                </TableRow>
            </TableHeader>
            <TableBody>
                {subscribers.map((subscriber) => (
                    <SubscriberRow
                        key={subscriber.uuid}
                        subscriber={subscriber}
                        routeArgs={routeArgs}
                        canManage={canManage}
                        tags={tags}
                        selected={selected}
                        onSelectedChange={onSelectedChange}
                    />
                ))}
            </TableBody>
        </Table>
    );
}

function SubscriberRow({
    subscriber,
    routeArgs,
    canManage,
    tags,
    selected,
    onSelectedChange,
}: {
    subscriber: Subscriber;
    routeArgs: [string, string];
    canManage: boolean;
    tags: Tag[];
    selected: Set<string>;
    onSelectedChange: (selected: Set<string>) => void;
}) {
    const [hoverOpen, setHoverOpen] = useState(false);
    const [editOpen, setEditOpen] = useState(false);
    const [editGeneration, setEditGeneration] = useState(0);

    const openEdit = () => {
        setHoverOpen(false);
        setTimeout(() => {
            setEditGeneration((count) => count + 1);
            setEditOpen(true);
        }, 0);
    };

    return (
        <TableRow data-test="subscriber-row">
            {canManage && (
                <TableCell>
                    <Checkbox
                        aria-label={`Select ${subscriber.email}`}
                        data-test="subscriber-row-select"
                        checked={selected.has(subscriber.uuid)}
                        onCheckedChange={(checked) => {
                            const next = new Set(selected);

                            if (checked === true) {
                                next.add(subscriber.uuid);
                            } else {
                                next.delete(subscriber.uuid);
                            }

                            onSelectedChange(next);
                        }}
                    />
                </TableCell>
            )}
            <TableCell className="max-w-0">
                <SubscriberHoverCard
                    subscriber={subscriber}
                    href={showSubscriber.url([...routeArgs, subscriber.uuid])}
                    canManage={canManage}
                    onEdit={canManage ? openEdit : undefined}
                    open={hoverOpen}
                    onOpenChange={(next) => {
                        if (editOpen) {
                            setHoverOpen(false);

                            return;
                        }

                        setHoverOpen(next);
                    }}
                />
            </TableCell>
            <TableCell>
                <div className="flex items-center gap-1.5 text-muted-foreground">
                    <HugeiconsIcon
                        icon={subscriberSourceIcon(subscriber)}
                        className="size-3.5"
                    />
                    {subscriberSourceLabel(subscriber)}
                </div>
            </TableCell>
            <TableCell>
                {subscriber.status === 'subscribed' ? (
                    <Badge variant="success">Subscribed</Badge>
                ) : (
                    <Badge variant="secondary">Unsubscribed</Badge>
                )}
            </TableCell>
            <TableCell className="min-w-40">
                {subscriber.tags.length > 0 ? (
                    <div className="flex flex-wrap gap-1">
                        {subscriber.tags
                            .slice(0, MAX_VISIBLE_TAGS)
                            .map((tag) => (
                                <Badge
                                    key={tag.uuid}
                                    variant={tagBadgeVariant(tag.color)}
                                >
                                    {tag.name}
                                </Badge>
                            ))}
                        {subscriber.tags.length > MAX_VISIBLE_TAGS && (
                            <Badge
                                variant="default"
                                title={subscriber.tags
                                    .slice(MAX_VISIBLE_TAGS)
                                    .map((tag) => tag.name)
                                    .join(', ')}
                            >
                                +{subscriber.tags.length - MAX_VISIBLE_TAGS}
                            </Badge>
                        )}
                    </div>
                ) : (
                    <span className="text-muted-foreground">—</span>
                )}
            </TableCell>
            <TableCell
                className="text-muted-foreground"
                title={
                    subscriber.subscribed_at
                        ? new Date(subscriber.subscribed_at).toLocaleString()
                        : undefined
                }
            >
                {subscriber.subscribed_at
                    ? formatRelativeTime(subscriber.subscribed_at)
                    : '—'}
            </TableCell>
            {canManage && (
                <TableCell className="text-right">
                    <SubscriberActions
                        subscriber={subscriber}
                        routeArgs={routeArgs}
                        tags={tags}
                        editOpen={editOpen}
                        editGeneration={editGeneration}
                        onEdit={openEdit}
                        onEditOpenChange={setEditOpen}
                    />
                </TableCell>
            )}
        </TableRow>
    );
}

function SegmentTable({
    segments,
    routeArgs,
    canManage,
    onEdit,
    onDelete,
}: {
    segments: Segment[];
    routeArgs: [string, string];
    canManage: boolean;
    onEdit: (segment: Segment) => void;
    onDelete: (segment: Segment) => void;
}) {
    return (
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead>Segment</TableHead>
                    <TableHead>Match</TableHead>
                    <TableHead>Rules</TableHead>
                    <TableHead>Matching</TableHead>
                    <TableHead className="text-right">Actions</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {segments.map((segment) => (
                    <TableRow key={segment.uuid} data-test="segment-row">
                        <TableCell>
                            <div className="flex flex-col">
                                <span className="font-medium">
                                    {segment.name}
                                </span>
                                {segment.description && (
                                    <span className="text-muted-foreground">
                                        {segment.description}
                                    </span>
                                )}
                            </div>
                        </TableCell>
                        <TableCell className="text-muted-foreground">
                            {segment.match_type === 'all'
                                ? 'All rules'
                                : 'Any rule'}
                        </TableCell>
                        <TableCell className="text-muted-foreground">
                            {segment.rules.length}
                        </TableCell>
                        <TableCell>
                            <Badge variant="secondary">
                                {segment.subscribers_count ?? 0} matching
                            </Badge>
                        </TableCell>
                        <TableCell className="text-right">
                            <DropdownMenu>
                                <DropdownMenuTrigger
                                    render={
                                        <Button
                                            size="icon"
                                            variant="ghost"
                                            aria-label={`Actions for ${segment.name}`}
                                        />
                                    }
                                >
                                    <HugeiconsIcon icon={MoreHorizontalIcon} />
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                    <DropdownMenuGroup>
                                        <DropdownMenuItem
                                            render={
                                                <Link
                                                    href={showSegment([
                                                        ...routeArgs,
                                                        segment.uuid,
                                                    ])}
                                                    prefetch
                                                />
                                            }
                                        >
                                            <HugeiconsIcon
                                                icon={ArrowRight01Icon}
                                            />
                                            View
                                        </DropdownMenuItem>
                                        {canManage && (
                                            <>
                                                <DropdownMenuItem
                                                    onClick={() =>
                                                        onEdit(segment)
                                                    }
                                                >
                                                    <HugeiconsIcon
                                                        icon={Edit03Icon}
                                                    />
                                                    Edit Rule
                                                </DropdownMenuItem>
                                                <DropdownMenuSeparator />
                                                <DropdownMenuItem
                                                    variant="destructive"
                                                    onClick={() =>
                                                        onDelete(segment)
                                                    }
                                                >
                                                    <HugeiconsIcon
                                                        icon={Delete02Icon}
                                                    />
                                                    Delete
                                                </DropdownMenuItem>
                                            </>
                                        )}
                                    </DropdownMenuGroup>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </TableCell>
                    </TableRow>
                ))}
            </TableBody>
        </Table>
    );
}

function SubscribeFormTable({
    forms,
    routeArgs,
    canManage,
}: {
    forms: SubscribeFormSummary[];
    routeArgs: [string, string];
    canManage: boolean;
}) {
    return (
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead>Form</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead>Subscribers</TableHead>
                    <TableHead className="text-right">Actions</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {forms.map((form) => (
                    <TableRow key={form.uuid} data-test="subscribe-form-row">
                        <TableCell>
                            <div className="flex flex-col">
                                <span className="font-medium">{form.name}</span>
                                <span className="text-muted-foreground">
                                    {form.headline}
                                </span>
                            </div>
                        </TableCell>
                        <TableCell>
                            {form.published ? (
                                <Badge variant="success">Published</Badge>
                            ) : (
                                <Badge variant="secondary">Draft</Badge>
                            )}
                        </TableCell>
                        <TableCell className="text-muted-foreground">
                            {form.subscribers_count}
                        </TableCell>
                        <TableCell className="text-right">
                            <DropdownMenu>
                                <DropdownMenuTrigger
                                    render={
                                        <Button
                                            size="icon"
                                            variant="ghost"
                                            aria-label={`Actions for ${form.name}`}
                                        />
                                    }
                                >
                                    <HugeiconsIcon icon={MoreHorizontalIcon} />
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                    <DropdownMenuGroup>
                                        <DropdownMenuItem
                                            render={
                                                <Link
                                                    href={editSubscribeForm([
                                                        ...routeArgs,
                                                        form.uuid,
                                                    ])}
                                                    prefetch
                                                />
                                            }
                                        >
                                            <HugeiconsIcon icon={Edit03Icon} />
                                            {canManage ? 'Edit' : 'View'}
                                        </DropdownMenuItem>
                                    </DropdownMenuGroup>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </TableCell>
                    </TableRow>
                ))}
            </TableBody>
        </Table>
    );
}

function SubscriberActions({
    subscriber,
    routeArgs,
    tags,
    editOpen,
    editGeneration,
    onEdit,
    onEditOpenChange,
}: {
    subscriber: Subscriber;
    routeArgs: [string, string];
    tags: Tag[];
    editOpen: boolean;
    editGeneration: number;
    onEdit: () => void;
    onEditOpenChange: (open: boolean) => void;
}) {
    const args = [...routeArgs, subscriber.uuid] as [string, string, string];
    const [lifecycleOpen, setLifecycleOpen] = useState(false);
    const [deleteOpen, setDeleteOpen] = useState(false);
    const [consentConfirmed, setConsentConfirmed] = useState(false);
    const isSubscribed = subscriber.status === 'subscribed';

    return (
        <div className="flex justify-end gap-2">
            <SubscriberDialog
                key={`edit-${subscriber.uuid}-${editGeneration}`}
                open={editOpen}
                onOpenChange={onEditOpenChange}
                routeArgs={routeArgs}
                subscriber={subscriber}
                tags={tags}
            />
            <DropdownMenu>
                <DropdownMenuTrigger
                    render={
                        <Button
                            size="icon"
                            variant="ghost"
                            aria-label="Subscriber actions"
                        />
                    }
                >
                    <HugeiconsIcon icon={MoreHorizontalIcon} />
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    <DropdownMenuGroup>
                        <DropdownMenuItem
                            render={
                                <Link
                                    href={showSubscriber.url(args)}
                                    prefetch
                                />
                            }
                        >
                            <HugeiconsIcon icon={UserIcon} />
                            View profile
                        </DropdownMenuItem>
                        <DropdownMenuItem onClick={onEdit}>
                            <HugeiconsIcon icon={Edit03Icon} />
                            Edit
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            onClick={() => setLifecycleOpen(true)}
                        >
                            <HugeiconsIcon
                                icon={
                                    isSubscribed
                                        ? UserRemove01Icon
                                        : UserCheck01Icon
                                }
                            />
                            {isSubscribed ? 'Unsubscribe' : 'Resubscribe'}
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            variant="destructive"
                            onClick={() => setDeleteOpen(true)}
                        >
                            <HugeiconsIcon icon={Delete02Icon} />
                            Delete
                        </DropdownMenuItem>
                    </DropdownMenuGroup>
                </DropdownMenuContent>
            </DropdownMenu>
            <AlertDialog
                open={lifecycleOpen}
                onOpenChange={(open) => {
                    setLifecycleOpen(open);

                    if (!open) {
                        setConsentConfirmed(false);
                    }
                }}
            >
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>
                            {isSubscribed
                                ? 'Unsubscribe this contact?'
                                : 'Resubscribe this contact?'}
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            {isSubscribed
                                ? `${subscriber.email} will stop receiving future marketing email.`
                                : `Confirm that ${subscriber.email} has given permission to receive marketing email again.`}
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    {!isSubscribed && (
                        <Field orientation="horizontal">
                            <Checkbox
                                id={`resubscribe-consent-${subscriber.uuid}`}
                                checked={consentConfirmed}
                                onCheckedChange={(checked) =>
                                    setConsentConfirmed(checked === true)
                                }
                            />
                            <FieldLabel
                                htmlFor={`resubscribe-consent-${subscriber.uuid}`}
                            >
                                Marketing consent confirmed
                            </FieldLabel>
                        </Field>
                    )}
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction
                            disabled={!isSubscribed && !consentConfirmed}
                            onClick={() =>
                                router.patch(
                                    isSubscribed
                                        ? unsubscribe.url(args)
                                        : resubscribe.url(args),
                                    isSubscribed
                                        ? {}
                                        : { consent_confirmed: true },
                                    {
                                        preserveScroll: true,
                                        onSuccess: () => {
                                            setLifecycleOpen(false);
                                            setConsentConfirmed(false);
                                        },
                                    },
                                )
                            }
                        >
                            {isSubscribed ? 'Unsubscribe' : 'Resubscribe'}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
            <AlertDialog open={deleteOpen} onOpenChange={setDeleteOpen}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>
                            Remove contact from audience?
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            This permanently removes {subscriber.email} from
                            this audience and its consent record.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction
                            variant="destructive"
                            onClick={() =>
                                router.delete(destroySubscriber.url(args))
                            }
                        >
                            Delete permanently
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </div>
    );
}

function emptySegmentForm() {
    return {
        name: '',
        description: '',
        match_type: 'all' as 'all' | 'any',
        rules: [
            { field: 'status', operator: 'equals', value: 'subscribed' },
        ] as SegmentRule[],
    };
}

function SegmentDialog({
    open,
    onOpenChange,
    routeArgs,
    forms,
    segment,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    routeArgs: [string, string];
    forms: SubscribeFormSummary[];
    segment?: Segment | null;
}) {
    const form = useForm(emptySegmentForm());

    useEffect(() => {
        if (!open) {
            return;
        }

        form.setData(
            segment
                ? {
                      name: segment.name,
                      description: segment.description || '',
                      match_type: segment.match_type,
                      rules: segment.rules,
                  }
                : emptySegmentForm(),
        );
        form.clearErrors();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, segment]);

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        const onSuccess = () => {
            form.reset();
            onOpenChange(false);
        };

        if (segment) {
            form.patch(updateSegment.url([...routeArgs, segment.uuid]), {
                preserveScroll: true,
                onSuccess,
            });

            return;
        }

        form.post(storeSegment.url(routeArgs), { onSuccess });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="w-2xl">
                <DialogHeader>
                    <DialogTitle>
                        {segment ? 'Edit Rule' : 'Create dynamic segment'}
                    </DialogTitle>
                    <DialogDescription>
                        {segment
                            ? 'Subscribers are recomputed automatically when you save.'
                            : 'Subscribers enter and leave this segment automatically as their data changes.'}
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit}>
                    <SegmentRuleFields
                        data={form.data}
                        setData={form.setData}
                        errors={form.errors}
                        forms={forms}
                    />
                    <DialogFooter className="mt-6">
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && (
                                <Spinner data-icon="inline-start" />
                            )}
                            {segment ? 'Save segment' : 'Create segment'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function SubscribeFormDialog({
    open,
    onOpenChange,
    routeArgs,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    routeArgs: [string, string];
}) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Create subscribe form</DialogTitle>
                    <DialogDescription>
                        Start with the default Maildun design, then preview and
                        publish it.
                    </DialogDescription>
                </DialogHeader>
                <Form
                    {...storeSubscribeForm.form(routeArgs)}
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ errors, processing }) => (
                        <>
                            <FieldGroup>
                                <Field data-invalid={Boolean(errors.name)}>
                                    <FieldLabel htmlFor="form-name">
                                        Internal name
                                    </FieldLabel>
                                    <Input
                                        id="form-name"
                                        name="name"
                                        placeholder="Website footer"
                                        aria-invalid={Boolean(errors.name)}
                                    />
                                    <FieldError>{errors.name}</FieldError>
                                </Field>
                                <Field data-invalid={Boolean(errors.headline)}>
                                    <FieldLabel htmlFor="form-headline">
                                        Headline
                                    </FieldLabel>
                                    <Input
                                        id="form-headline"
                                        name="headline"
                                        defaultValue="Join our newsletter"
                                        placeholder="Join our newsletter"
                                        aria-invalid={Boolean(errors.headline)}
                                    />
                                    <FieldError>{errors.headline}</FieldError>
                                </Field>
                                <input
                                    type="hidden"
                                    name="button_label"
                                    value="Subscribe"
                                />
                                <input
                                    type="hidden"
                                    name="success_message"
                                    value="Thanks for subscribing!"
                                />
                                <input
                                    type="hidden"
                                    name="consent_text"
                                    value="I agree to receive marketing emails."
                                />
                            </FieldGroup>
                            <DialogFooter className="mt-6">
                                <Button type="submit" disabled={processing}>
                                    {processing && (
                                        <Spinner data-icon="inline-start" />
                                    )}
                                    Create form
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
