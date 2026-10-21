import {
    ArrowDown01Icon,
    Building06Icon,
    Delete02Icon,
    TranslateIcon,
    UserGroupIcon,
    UserIcon,
} from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { ActiveFilters } from '@/components/active-filters';
import { ContactDialog } from '@/components/contact-dialog';
import { ContactHoverCard } from '@/components/contact-hover-card';
import { ContactImportMenu } from '@/components/contact-import-menu';
import { FilterMenu } from '@/components/filter-menu';
import { ListSearch } from '@/components/list-search';
import { Paginator } from '@/components/paginator';
import { SelectionActionBar } from '@/components/selection-action-bar';
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
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
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
import { formatRelativeTime } from '@/lib/format';
import { tagBadgeVariant } from '@/lib/tags';
import { show as showCompany } from '@/routes/companies';
import { bulkDestroy, bulkUnsubscribe, index, show } from '@/routes/contacts';
import { show as showContactExport } from '@/routes/contacts/exports';
import type { ContactIndexProps, ContactSummary } from '@/types/contacts';

const SORT_LABELS = {
    newest: 'Newest',
    oldest: 'Oldest',
    name: 'Name',
} as const;

export default function ContactsIndex({
    contacts,
    companies,
    audiences,
    addableAudiences,
    canAddToAudiences,
    tags,
    languages,
    filters,
    canManage,
}: ContactIndexProps) {
    const { currentTeam } = usePage().props;
    const [createOpen, setCreateOpen] = useState(false);
    const [selected, setSelected] = useState<Set<string>>(new Set());
    const [confirmDeleteOpen, setConfirmDeleteOpen] = useState(false);
    const { visit, clear, clearAll, searchKey } = useListFilters({
        url: currentTeam ? index.url(currentTeam.slug) : '',
        filters,
        empty: { search: '', company: '', audience: '', language: '' },
        searchField: 'search',
    });

    if (!currentTeam) {
        return null;
    }

    const pageIds = contacts.data.map((contact) => contact.uuid);
    const allSelected =
        pageIds.length > 0 && pageIds.every((id) => selected.has(id));
    const someSelected = pageIds.some((id) => selected.has(id));

    const toggleSelected = (ids: string[], checked: boolean): void => {
        const next = new Set(selected);

        ids.forEach((id) => (checked ? next.add(id) : next.delete(id)));
        setSelected(next);
    };

    const companyOptions = [
        { value: 'assigned', label: 'Assigned to a company' },
        { value: 'unassigned', label: 'No company' },
        ...companies.map((company) => ({
            value: company.uuid,
            label: company.name,
        })),
    ];

    return (
        <>
            <Head title="Contacts" />
            <div className="flex flex-1 flex-col gap-6">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                    <div className="flex flex-col gap-1">
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Contacts
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            People are shared across audiences, tags, and
                            companies.
                        </p>
                    </div>
                    {canManage ? (
                        <div className="flex flex-wrap items-center gap-2">
                            <ContactImportMenu
                                teamSlug={currentTeam.slug}
                                exportCsvUrl={showContactExport.url(
                                    [currentTeam.slug, 'csv'],
                                    { query: filters },
                                )}
                                exportXlsUrl={showContactExport.url(
                                    [currentTeam.slug, 'xls'],
                                    { query: filters },
                                )}
                            />
                            <ContactDialog
                                teamSlug={currentTeam.slug}
                                companies={companies}
                                tags={tags}
                                audiences={addableAudiences}
                                lacksAudienceAccess={!canAddToAudiences}
                                open={createOpen}
                                onOpenChange={setCreateOpen}
                            />
                        </div>
                    ) : null}
                </div>

                <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <FilterMenu
                        fields={[
                            {
                                key: 'company',
                                icon: Building06Icon,
                                label: 'Company',
                                value: filters.company,
                                allLabel: 'All companies',
                                options: companyOptions,
                                onValueChange: (company) => visit({ company }),
                            },
                            {
                                key: 'audience',
                                icon: UserGroupIcon,
                                label: 'Audience',
                                value: filters.audience,
                                allLabel: 'All audiences',
                                options: audiences.map((audience) => ({
                                    value: audience.uuid,
                                    label: audience.name,
                                })),
                                onValueChange: (audience) =>
                                    visit({ audience }),
                            },
                            {
                                key: 'language',
                                icon: TranslateIcon,
                                label: 'Language',
                                value: filters.language,
                                allLabel: 'All languages',
                                options: languages,
                                onValueChange: (language) =>
                                    visit({
                                        language:
                                            language as ContactIndexProps['filters']['language'],
                                    }),
                            },
                        ]}
                    />
                    <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
                        <ListSearch
                            key={searchKey}
                            value={filters.search}
                            onSearch={(search) => visit({ search })}
                            placeholder="Search contacts"
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
                                    onValueChange={(sort) =>
                                        visit({
                                            sort: sort as keyof typeof SORT_LABELS,
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

                <ActiveFilters
                    filters={[
                        ...(filters.search
                            ? [
                                  {
                                      key: 'search',
                                      field: 'Search',
                                      value: filters.search,
                                      onClear: () => clear({ search: '' }),
                                  },
                              ]
                            : []),
                        ...(filters.company
                            ? [
                                  {
                                      key: 'company',
                                      field: 'Company',
                                      value:
                                          companyOptions.find(
                                              (option) =>
                                                  option.value ===
                                                  filters.company,
                                          )?.label ?? filters.company,
                                      onClear: () => clear({ company: '' }),
                                  },
                              ]
                            : []),
                        ...(filters.audience
                            ? [
                                  {
                                      key: 'audience',
                                      field: 'Audience',
                                      value:
                                          audiences.find(
                                              (audience) =>
                                                  audience.uuid ===
                                                  filters.audience,
                                          )?.name ?? filters.audience,
                                      onClear: () => clear({ audience: '' }),
                                  },
                              ]
                            : []),
                        ...(filters.language
                            ? [
                                  {
                                      key: 'language',
                                      field: 'Language',
                                      value:
                                          languages.find(
                                              (language) =>
                                                  language.value ===
                                                  filters.language,
                                          )?.label ?? filters.language,
                                      onClear: () => clear({ language: '' }),
                                  },
                              ]
                            : []),
                    ]}
                    onClearAll={clearAll}
                />

                {contacts.total === 0 ? (
                    <Empty>
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <HugeiconsIcon icon={UserIcon} />
                            </EmptyMedia>
                            <EmptyTitle>No contacts yet</EmptyTitle>
                            <EmptyDescription>
                                Add a person directly, or they will be created
                                when they join an audience.
                            </EmptyDescription>
                        </EmptyHeader>
                    </Empty>
                ) : contacts.data.length === 0 ? (
                    <Empty>
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <HugeiconsIcon icon={UserIcon} />
                            </EmptyMedia>
                            <EmptyTitle>No matching contacts</EmptyTitle>
                            <EmptyDescription>
                                Try another search or filter.
                            </EmptyDescription>
                        </EmptyHeader>
                    </Empty>
                ) : (
                    <>
                        <Table
                            className="table-fixed"
                            footer={
                                <Paginator paginator={contacts} showSummary />
                            }
                        >
                            <TableHeader>
                                <TableRow>
                                    {canManage && (
                                        <TableHead className="w-10">
                                            <Checkbox
                                                aria-label="Select all"
                                                data-test="contact-select-all"
                                                checked={allSelected}
                                                indeterminate={
                                                    someSelected && !allSelected
                                                }
                                                onCheckedChange={(checked) =>
                                                    toggleSelected(
                                                        pageIds,
                                                        checked === true,
                                                    )
                                                }
                                            />
                                        </TableHead>
                                    )}
                                    <TableHead className="w-80">
                                        Contact
                                    </TableHead>
                                    <TableHead>Company</TableHead>
                                    <TableHead>Tags</TableHead>
                                    <TableHead className="w-28">
                                        Audiences
                                    </TableHead>
                                    <TableHead className="w-28">
                                        Created
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {contacts.data.map((contact) => (
                                    <ContactRow
                                        key={contact.uuid}
                                        contact={contact}
                                        teamSlug={currentTeam.slug}
                                        companies={companies}
                                        tags={tags}
                                        canManage={canManage}
                                        isSelected={selected.has(contact.uuid)}
                                        onSelectedChange={(checked) =>
                                            toggleSelected(
                                                [contact.uuid],
                                                checked,
                                            )
                                        }
                                    />
                                ))}
                            </TableBody>
                        </Table>
                    </>
                )}
                {canManage && (
                    <>
                        <SelectionActionBar
                            count={selected.size}
                            noun={['contact', 'contacts']}
                            onClear={() => setSelected(new Set())}
                        >
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => {
                                    router.patch(
                                        bulkUnsubscribe.url(currentTeam.slug),
                                        { ids: [...selected] },
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
                                onClick={() => setConfirmDeleteOpen(true)}
                            >
                                <HugeiconsIcon
                                    icon={Delete02Icon}
                                    data-icon="inline-start"
                                />
                                Delete
                            </Button>
                        </SelectionActionBar>
                        <AlertDialog
                            open={confirmDeleteOpen}
                            onOpenChange={setConfirmDeleteOpen}
                        >
                            <AlertDialogContent>
                                <AlertDialogHeader>
                                    <AlertDialogTitle>
                                        Delete {selected.size}{' '}
                                        {selected.size === 1
                                            ? 'contact'
                                            : 'contacts'}
                                        ?
                                    </AlertDialogTitle>
                                    <AlertDialogDescription>
                                        They are removed from every audience
                                        they belong to. This cannot be undone.
                                    </AlertDialogDescription>
                                </AlertDialogHeader>
                                <AlertDialogFooter>
                                    <AlertDialogCancel>
                                        Cancel
                                    </AlertDialogCancel>
                                    <AlertDialogAction
                                        variant="destructive"
                                        onClick={() => {
                                            router.delete(
                                                bulkDestroy.url(
                                                    currentTeam.slug,
                                                ),
                                                {
                                                    data: {
                                                        ids: [...selected],
                                                    },
                                                    preserveScroll: true,
                                                    onSuccess: () =>
                                                        setSelected(new Set()),
                                                    onFinish: () =>
                                                        setConfirmDeleteOpen(
                                                            false,
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
                    </>
                )}
            </div>
        </>
    );
}

function ContactRow({
    contact,
    teamSlug,
    companies,
    tags,
    canManage,
    isSelected,
    onSelectedChange,
}: {
    contact: ContactSummary;
    teamSlug: string;
    companies: ContactIndexProps['companies'];
    tags: ContactIndexProps['tags'];
    canManage: boolean;
    isSelected: boolean;
    onSelectedChange: (checked: boolean) => void;
}) {
    const [hoverOpen, setHoverOpen] = useState(false);
    const [editOpen, setEditOpen] = useState(false);
    const [editGeneration, setEditGeneration] = useState(0);
    const href = show.url([teamSlug, contact.uuid]);

    const openEdit = (): void => {
        setHoverOpen(false);
        setTimeout(() => {
            setEditGeneration((count) => count + 1);
            setEditOpen(true);
        }, 0);
    };

    return (
        <TableRow data-test="contact-row">
            {canManage && (
                <TableCell>
                    <Checkbox
                        aria-label={`Select ${contact.email}`}
                        data-test="contact-row-select"
                        checked={isSelected}
                        onCheckedChange={(checked) =>
                            onSelectedChange(checked === true)
                        }
                    />
                </TableCell>
            )}
            <TableCell className="max-w-80">
                <ContactHoverCard
                    contact={contact}
                    href={href}
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
                {canManage && (
                    <ContactDialog
                        key={editGeneration}
                        teamSlug={teamSlug}
                        companies={companies}
                        tags={tags}
                        contact={contact}
                        open={editOpen}
                        onOpenChange={setEditOpen}
                    />
                )}
            </TableCell>
            <TableCell>
                {contact.company ? (
                    <Link
                        href={showCompany.url([teamSlug, contact.company.uuid])}
                        className="underline-offset-4 hover:underline"
                    >
                        {contact.company.name}
                    </Link>
                ) : (
                    <span className="text-muted-foreground">—</span>
                )}
            </TableCell>
            <TableCell>
                <div className="flex flex-wrap gap-1">
                    {contact.tags.slice(0, 3).map((tag) => (
                        <Badge
                            key={tag.uuid}
                            variant={tagBadgeVariant(tag.color)}
                        >
                            {tag.name}
                        </Badge>
                    ))}
                    {contact.tags.length > 3 ? (
                        <Badge variant="outline">
                            +{contact.tags.length - 3}
                        </Badge>
                    ) : null}
                </div>
            </TableCell>
            <TableCell>{contact.audiences_count}</TableCell>
            <TableCell className="text-muted-foreground">
                {contact.created_at
                    ? formatRelativeTime(contact.created_at)
                    : '—'}
            </TableCell>
        </TableRow>
    );
}
