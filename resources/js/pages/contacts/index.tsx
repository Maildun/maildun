import {
    ArrowDown01Icon,
    Building06Icon,
    UserGroupIcon,
    UserIcon,
} from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { Head, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { ActiveFilters } from '@/components/active-filters';
import { ContactDialog } from '@/components/contact-dialog';
import { ContactHoverCard } from '@/components/contact-hover-card';
import { ContactImportDialog } from '@/components/contact-import-dialog';
import { FilterMenu } from '@/components/filter-menu';
import { ListSearch } from '@/components/list-search';
import { Paginator } from '@/components/paginator';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
import { index, show } from '@/routes/contacts';
import { show as showContactExport } from '@/routes/contacts/exports';
import { store as storeContactImport } from '@/routes/contacts/imports';
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
    tags,
    contactImports,
    filters,
    canManage,
}: ContactIndexProps) {
    const { currentTeam } = usePage().props;
    const [createOpen, setCreateOpen] = useState(false);
    const { visit, clear, clearAll, searchKey } = useListFilters({
        url: currentTeam ? index.url(currentTeam.slug) : '',
        filters,
        empty: { search: '', company: '', audience: '' },
        searchField: 'search',
    });

    if (!currentTeam) {
        return null;
    }

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
                            <ContactImportDialog
                                action={storeContactImport.url(
                                    currentTeam.slug,
                                )}
                                imports={contactImports}
                                pollOnly={['contactImports', 'contacts']}
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
                                audiences={audiences}
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
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Contact</TableHead>
                                    <TableHead>Company</TableHead>
                                    <TableHead>Tags</TableHead>
                                    <TableHead>Audiences</TableHead>
                                    <TableHead>Created</TableHead>
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
                                    />
                                ))}
                            </TableBody>
                        </Table>
                        <Paginator paginator={contacts} />
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
}: {
    contact: ContactSummary;
    teamSlug: string;
    companies: ContactIndexProps['companies'];
    tags: ContactIndexProps['tags'];
    canManage: boolean;
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
            <TableCell className="max-w-0">
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
                {contact.company?.name ?? (
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
