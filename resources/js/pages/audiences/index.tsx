import {
    Add01Icon,
    ArrowDown01Icon,
    Delete02Icon,
    Edit03Icon,
    MoreHorizontalIcon,
    UserGroupIcon,
} from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { Form, Head, Link, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { ActiveFilters } from '@/components/active-filters';
import { DeleteAudienceDialog } from '@/components/delete-audience-dialog';
import { ListSearch } from '@/components/list-search';
import { Paginator } from '@/components/paginator';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
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
    FieldDescription,
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
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { useListFilters } from '@/hooks/use-list-filters';
import { formatRelativeTime } from '@/lib/format';
import { index, show, store, update } from '@/routes/audiences';
import type {
    AudienceIndexFilter,
    AudienceIndexFilters,
    AudienceIndexSort,
    AudienceSummary,
    Paginated,
} from '@/types/audiences';

type Props = {
    audiences: Paginated<AudienceSummary>;
    filters: AudienceIndexFilters;
    hasAudiences: boolean;
    canManage: boolean;
};

const FILTER_TABS: { value: AudienceIndexFilter; label: string }[] = [
    { value: 'all', label: 'All' },
    { value: 'active', label: 'Active' },
    { value: 'empty', label: 'Empty' },
    { value: 'segments', label: 'Segments' },
    { value: 'forms', label: 'Forms' },
];

const FILTER_TAB_LABELS: Record<string, string> = Object.fromEntries(
    FILTER_TABS.map((tab) => [tab.value, tab.label]),
);

const SORT_LABELS: Record<AudienceIndexSort, string> = {
    newest: 'Newest',
    oldest: 'Oldest',
    subscribers: 'Most subscribers',
};

export default function AudiencesIndex({
    audiences,
    filters,
    hasAudiences,
    canManage,
}: Props) {
    const { currentTeam } = usePage().props;
    const [createOpen, setCreateOpen] = useState(false);
    const [audienceToEdit, setAudienceToEdit] =
        useState<AudienceSummary | null>(null);
    const [audienceToDelete, setAudienceToDelete] =
        useState<AudienceSummary | null>(null);
    const {
        visit: visitIndex,
        clear: clearFilters,
        clearAll: clearAllFilters,
        searchKey,
    } = useListFilters<AudienceIndexFilters>({
        url: currentTeam ? index.url(currentTeam.slug) : '',
        filters,
        empty: { search: '', filter: 'all' },
        searchField: 'search',
    });

    if (!currentTeam) {
        return null;
    }

    return (
        <>
            <Head title="Audiences" />
            <div className="flex flex-1 flex-col gap-6">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                    <div className="flex flex-col gap-1">
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Audiences
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Organize contacts, saved segments, and signup forms
                            for this team.
                        </p>
                    </div>
                    {canManage && (
                        <CreateAudienceDialog
                            teamSlug={currentTeam.slug}
                            open={createOpen}
                            onOpenChange={setCreateOpen}
                        />
                    )}
                </div>

                {hasAudiences && (
                    <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                        <Tabs
                            value={filters.filter}
                            onValueChange={(value) =>
                                visitIndex({
                                    filter: value as AudienceIndexFilter,
                                })
                            }
                        >
                            <TabsList variant="sliding">
                                {FILTER_TABS.map((tab) => (
                                    <TabsTrigger
                                        key={tab.value}
                                        value={tab.value}
                                    >
                                        {tab.label}
                                    </TabsTrigger>
                                ))}
                            </TabsList>
                        </Tabs>
                        <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
                            <ListSearch
                                key={searchKey}
                                value={filters.search}
                                onSearch={(search) => visitIndex({ search })}
                                placeholder="Search audiences"
                                className="sm:min-w-64"
                            />
                            <DropdownMenu>
                                <DropdownMenuTrigger
                                    render={<Button variant="outline" />}
                                >
                                    <HugeiconsIcon
                                        icon={ArrowDown01Icon}
                                        data-icon="inline-start"
                                    />
                                    {SORT_LABELS[filters.sort]}
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                    <DropdownMenuRadioGroup
                                        value={filters.sort}
                                        onValueChange={(value) =>
                                            visitIndex({
                                                sort: value as AudienceIndexSort,
                                            })
                                        }
                                    >
                                        <DropdownMenuLabel>
                                            Sort by
                                        </DropdownMenuLabel>
                                        {Object.entries(SORT_LABELS).map(
                                            ([value, label]) => (
                                                <DropdownMenuRadioItem
                                                    key={value}
                                                    value={value}
                                                >
                                                    {label}
                                                </DropdownMenuRadioItem>
                                            ),
                                        )}
                                    </DropdownMenuRadioGroup>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>
                    </div>
                )}

                {hasAudiences && (
                    <ActiveFilters
                        filters={[
                            ...(filters.search !== ''
                                ? [
                                      {
                                          key: 'search',
                                          field: 'Search',
                                          value: filters.search,
                                          onClear: () =>
                                              clearFilters({ search: '' }),
                                      },
                                  ]
                                : []),
                            ...(filters.filter !== 'all'
                                ? [
                                      {
                                          key: 'filter',
                                          field: 'Audience',
                                          value:
                                              FILTER_TAB_LABELS[
                                                  filters.filter
                                              ] ?? filters.filter,
                                          onClear: () =>
                                              clearFilters({ filter: 'all' }),
                                      },
                                  ]
                                : []),
                        ]}
                        onClearAll={clearAllFilters}
                        clearTestId="clear-audience-filters"
                    />
                )}

                {!hasAudiences ? (
                    <Empty>
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <HugeiconsIcon icon={UserGroupIcon} />
                            </EmptyMedia>
                            <EmptyTitle>No audiences yet</EmptyTitle>
                            <EmptyDescription>
                                Create your first audience to start collecting
                                contacts.
                            </EmptyDescription>
                        </EmptyHeader>
                        {canManage && (
                            <EmptyContent>
                                <Button onClick={() => setCreateOpen(true)}>
                                    Create audience
                                </Button>
                            </EmptyContent>
                        )}
                    </Empty>
                ) : audiences.data.length === 0 ? (
                    <Empty>
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <HugeiconsIcon icon={UserGroupIcon} />
                            </EmptyMedia>
                            <EmptyTitle>No matching audiences</EmptyTitle>
                            <EmptyDescription>
                                Try another search, filter, or sort.
                            </EmptyDescription>
                        </EmptyHeader>
                    </Empty>
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Audience</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Subscribed</TableHead>
                                <TableHead>Segments</TableHead>
                                <TableHead>Forms</TableHead>
                                <TableHead>Created</TableHead>
                                <TableHead className="text-right">
                                    Actions
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {audiences.data.map((audience) => (
                                <TableRow
                                    key={audience.uuid}
                                    data-test="audience-row"
                                >
                                    <TableCell>
                                        <div className="flex items-center gap-3">
                                            <Avatar className="size-8 rounded-md after:rounded-md">
                                                <AvatarImage
                                                    src={audience.avatar}
                                                    alt=""
                                                    className="rounded-md"
                                                />
                                                <AvatarFallback className="rounded-md">
                                                    {audience.name
                                                        .charAt(0)
                                                        .toUpperCase()}
                                                </AvatarFallback>
                                            </Avatar>
                                            <div className="flex min-w-0 flex-col">
                                                <Link
                                                    href={show([
                                                        currentTeam.slug,
                                                        audience.uuid,
                                                    ])}
                                                    prefetch
                                                    className="font-medium underline-offset-4 hover:underline"
                                                    data-test="audience-name-link"
                                                >
                                                    {audience.name}
                                                </Link>
                                                <span className="text-muted-foreground">
                                                    {audience.description ||
                                                        'No description provided.'}
                                                </span>
                                            </div>
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        {audience.subscribed_count > 0 ? (
                                            <Badge variant="success">
                                                Active
                                            </Badge>
                                        ) : (
                                            <Badge variant="default">
                                                Empty
                                            </Badge>
                                        )}
                                    </TableCell>
                                    <TableCell>
                                        {audience.subscribed_count}
                                    </TableCell>
                                    <TableCell>
                                        {audience.segments_count}
                                    </TableCell>
                                    <TableCell>
                                        {audience.forms_count}
                                    </TableCell>
                                    <TableCell
                                        title={new Date(
                                            audience.created_at,
                                        ).toLocaleString()}
                                    >
                                        {formatRelativeTime(
                                            audience.created_at,
                                        )}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <AudienceRowActions
                                            audience={audience}
                                            teamSlug={currentTeam.slug}
                                            canManage={canManage}
                                            onQuickEdit={setAudienceToEdit}
                                            onDelete={setAudienceToDelete}
                                        />
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}

                <Paginator paginator={audiences} />
            </div>

            <QuickEditAudienceDialog
                teamSlug={currentTeam.slug}
                audience={audienceToEdit}
                onOpenChange={(open) => {
                    if (!open) {
                        setAudienceToEdit(null);
                    }
                }}
            />
            <DeleteAudienceDialog
                teamSlug={currentTeam.slug}
                audience={audienceToDelete}
                open={Boolean(audienceToDelete)}
                onOpenChange={(open) => {
                    if (!open) {
                        setAudienceToDelete(null);
                    }
                }}
            />
        </>
    );
}

function AudienceRowActions({
    audience,
    teamSlug,
    canManage,
    onQuickEdit,
    onDelete,
}: {
    audience: AudienceSummary;
    teamSlug: string;
    canManage: boolean;
    onQuickEdit: (audience: AudienceSummary) => void;
    onDelete: (audience: AudienceSummary) => void;
}) {
    return (
        <DropdownMenu>
            <DropdownMenuTrigger
                render={
                    <Button
                        size="icon"
                        variant="ghost"
                        aria-label={`Actions for ${audience.name}`}
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
                                href={show([teamSlug, audience.uuid])}
                                prefetch
                            />
                        }
                    >
                        <HugeiconsIcon icon={UserGroupIcon} />
                        Manage
                    </DropdownMenuItem>
                    {canManage && (
                        <>
                            <DropdownMenuItem
                                onClick={() => onQuickEdit(audience)}
                            >
                                <HugeiconsIcon icon={Edit03Icon} />
                                Quick edit
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                                variant="destructive"
                                onClick={() => onDelete(audience)}
                            >
                                <HugeiconsIcon icon={Delete02Icon} />
                                Delete
                            </DropdownMenuItem>
                        </>
                    )}
                </DropdownMenuGroup>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

function CreateAudienceDialog({
    teamSlug,
    open,
    onOpenChange,
}: {
    teamSlug: string;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogTrigger render={<Button />}>
                <HugeiconsIcon icon={Add01Icon} data-icon="inline-start" />
                New audience
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Create audience</DialogTitle>
                    <DialogDescription>
                        Start a separate subscriber list for a product,
                        publication, or community.
                    </DialogDescription>
                </DialogHeader>
                <Form
                    {...store.form(teamSlug)}
                    resetOnSuccess
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ errors, processing }) => (
                        <>
                            <FieldGroup>
                                <Field data-invalid={Boolean(errors.name)}>
                                    <FieldLabel htmlFor="name">Name</FieldLabel>
                                    <Input
                                        id="name"
                                        name="name"
                                        autoFocus
                                        aria-invalid={Boolean(errors.name)}
                                        placeholder="Product newsletter"
                                    />
                                    <FieldError>{errors.name}</FieldError>
                                </Field>
                                <Field
                                    data-invalid={Boolean(errors.description)}
                                >
                                    <FieldLabel htmlFor="description">
                                        Description
                                    </FieldLabel>
                                    <Textarea
                                        id="description"
                                        name="description"
                                        aria-invalid={Boolean(
                                            errors.description,
                                        )}
                                        placeholder="Who belongs in this audience?"
                                    />
                                    <FieldDescription>
                                        Optional, visible only to your team.
                                    </FieldDescription>
                                    <FieldError>
                                        {errors.description}
                                    </FieldError>
                                </Field>
                            </FieldGroup>
                            <DialogFooter className="mt-6">
                                <Button type="submit" disabled={processing}>
                                    {processing && (
                                        <Spinner data-icon="inline-start" />
                                    )}
                                    Create audience
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function QuickEditAudienceDialog({
    teamSlug,
    audience,
    onOpenChange,
}: {
    teamSlug: string;
    audience: AudienceSummary | null;
    onOpenChange: (open: boolean) => void;
}) {
    if (!audience) {
        return null;
    }

    return (
        <Dialog open onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Quick edit</DialogTitle>
                    <DialogDescription>
                        Update the name and description for this audience.
                    </DialogDescription>
                </DialogHeader>
                <Form
                    {...update.form([teamSlug, audience.uuid])}
                    resetOnSuccess
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ errors, processing }) => (
                        <>
                            <FieldGroup>
                                <Field data-invalid={Boolean(errors.name)}>
                                    <FieldLabel htmlFor="quick-edit-name">
                                        Name
                                    </FieldLabel>
                                    <Input
                                        id="quick-edit-name"
                                        name="name"
                                        defaultValue={audience.name}
                                        aria-invalid={Boolean(errors.name)}
                                        placeholder="Product newsletter"
                                    />
                                    <FieldError>{errors.name}</FieldError>
                                </Field>
                                <Field
                                    data-invalid={Boolean(errors.description)}
                                >
                                    <FieldLabel htmlFor="quick-edit-description">
                                        Description
                                    </FieldLabel>
                                    <Textarea
                                        id="quick-edit-description"
                                        name="description"
                                        defaultValue={
                                            audience.description ?? ''
                                        }
                                        aria-invalid={Boolean(
                                            errors.description,
                                        )}
                                        placeholder="Who belongs in this audience?"
                                    />
                                    <FieldError>
                                        {errors.description}
                                    </FieldError>
                                </Field>
                            </FieldGroup>
                            <DialogFooter className="mt-6">
                                <DialogClose
                                    render={<Button variant="outline" />}
                                >
                                    Cancel
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    {processing && (
                                        <Spinner data-icon="inline-start" />
                                    )}
                                    Save
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

AudiencesIndex.layout = (props: { currentTeam?: { slug: string } | null }) => ({
    breadcrumbs: [
        {
            title: 'Audiences',
            href: props.currentTeam ? index(props.currentTeam.slug) : '/',
        },
    ],
});
