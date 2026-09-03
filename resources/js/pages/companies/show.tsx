import {
    Building06Icon,
    UserGroupIcon,
    UserIcon,
} from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { ActiveFilters } from '@/components/active-filters';
import { CompanyDialog } from '@/components/company-dialog';
import { ContactDialog } from '@/components/contact-dialog';
import { ContactHoverCard } from '@/components/contact-hover-card';
import { FilterMenu } from '@/components/filter-menu';
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
import { formatRelativeTime } from '@/lib/format';
import { tagBadgeVariant } from '@/lib/tags';
import { destroy, show } from '@/routes/companies';
import { show as showContact } from '@/routes/contacts';
import type { Tag } from '@/types/audiences';
import type {
    Company,
    CompanyContact,
    CompanyContactFilters,
} from '@/types/companies';
import type { CompanyOption } from '@/types/contacts';

type Props = {
    company: Company;
    companies: CompanyOption[];
    audiences: CompanyOption[];
    tags: Tag[];
    filters: CompanyContactFilters;
    canManage: boolean;
};

export default function CompanyShow({
    company,
    companies,
    audiences,
    tags,
    filters,
    canManage,
}: Props) {
    const { currentTeam } = usePage().props;
    const [editOpen, setEditOpen] = useState(false);
    const [deleteOpen, setDeleteOpen] = useState(false);
    const { visit, clear, clearAll, searchKey } = useListFilters({
        url: currentTeam ? show.url([currentTeam.slug, company.uuid]) : '',
        filters,
        empty: { search: '', audience: '' },
        searchField: 'search',
    });

    if (!currentTeam) {
        return null;
    }

    const routeArgs: [string, string] = [currentTeam.slug, company.uuid];

    return (
        <>
            <Head title={company.name} />
            <div className="flex flex-1 flex-col gap-6">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                    <div className="flex items-center gap-3">
                        <div className="grid size-12 place-items-center rounded-lg bg-muted">
                            {company.favicon ? (
                                <img
                                    src={company.favicon}
                                    alt=""
                                    className="size-12 rounded-lg bg-white object-contain p-1.5"
                                />
                            ) : (
                                <HugeiconsIcon
                                    icon={Building06Icon}
                                    className="size-6"
                                />
                            )}
                        </div>
                        <div>
                            <h1 className="text-2xl font-semibold tracking-tight">
                                {company.name}
                            </h1>
                            <p className="text-sm text-muted-foreground">
                                {company.contacts_count} linked contacts
                            </p>
                        </div>
                    </div>
                    {canManage ? (
                        <div className="flex gap-2">
                            <Button
                                variant="outline"
                                onClick={() => setEditOpen(true)}
                            >
                                Edit
                            </Button>
                            <Button
                                variant="destructive"
                                onClick={() => setDeleteOpen(true)}
                            >
                                Delete
                            </Button>
                        </div>
                    ) : null}
                </div>
                <Card>
                    <CardHeader>
                        <CardTitle>Domains</CardTitle>
                        <CardDescription>
                            Contacts with an automatic association are matched
                            to these domains.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-wrap gap-2">
                        {company.domains.map((domain) => (
                            <Badge key={domain} variant="outline">
                                {domain}
                            </Badge>
                        ))}
                    </CardContent>
                </Card>
                <div className="flex flex-col gap-1">
                    <h2 className="text-lg font-semibold">Contacts</h2>
                    <p className="text-sm text-muted-foreground">
                        All contacts currently associated with this company.
                    </p>
                </div>
                <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <FilterMenu
                        testId="company-contact-filter-button"
                        fields={[
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
                                testId: 'company-contact-audience-filter',
                            },
                        ]}
                    />
                    <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
                        <ListSearch
                            key={searchKey}
                            value={filters.search}
                            onSearch={(search) => visit({ search })}
                            placeholder="Search contacts"
                            label="Search company contacts"
                            className="sm:min-w-64"
                        />
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
                    clearTestId="clear-company-contact-filters"
                />
                {company.contacts_count === 0 ? (
                    <CompanyContactsEmpty
                        title="No contacts yet"
                        description="Contacts associated with this company will appear here."
                    />
                ) : company.contacts.data.length === 0 ? (
                    <CompanyContactsEmpty
                        title="No matching contacts"
                        description="Try another search or filter."
                    />
                ) : (
                    <>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Contact</TableHead>
                                    <TableHead>Tags</TableHead>
                                    <TableHead>Audiences</TableHead>
                                    <TableHead>Created</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {company.contacts.data.map((contact) => (
                                    <CompanyContactRow
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
                        <Paginator paginator={company.contacts} />
                    </>
                )}
            </div>
            {canManage ? (
                <CompanyDialog
                    teamSlug={currentTeam.slug}
                    company={company}
                    open={editOpen}
                    onOpenChange={setEditOpen}
                />
            ) : null}
            <AlertDialog open={deleteOpen} onOpenChange={setDeleteOpen}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>
                            Delete this company?
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            The company domains will be removed. Its contacts
                            will remain and be checked for another matching
                            company.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction
                            type="button"
                            variant="destructive"
                            onClick={() =>
                                router.delete(destroy.url(routeArgs))
                            }
                        >
                            Delete company
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </>
    );
}

function CompanyContactsEmpty({
    title,
    description,
}: {
    title: string;
    description: string;
}) {
    return (
        <Empty>
            <EmptyHeader>
                <EmptyMedia variant="icon">
                    <HugeiconsIcon icon={UserIcon} />
                </EmptyMedia>
                <EmptyTitle>{title}</EmptyTitle>
                <EmptyDescription>{description}</EmptyDescription>
            </EmptyHeader>
        </Empty>
    );
}

function CompanyContactRow({
    contact,
    teamSlug,
    companies,
    tags,
    canManage,
}: {
    contact: CompanyContact;
    teamSlug: string;
    companies: CompanyOption[];
    tags: Tag[];
    canManage: boolean;
}) {
    const [hoverOpen, setHoverOpen] = useState(false);
    const [editOpen, setEditOpen] = useState(false);
    const [editGeneration, setEditGeneration] = useState(0);
    const href = showContact.url([teamSlug, contact.uuid]);
    const audienceNames = contact.audiences
        .map((audience) => audience.name)
        .join(', ');

    const openEdit = (): void => {
        setHoverOpen(false);
        setTimeout(() => {
            setEditGeneration((count) => count + 1);
            setEditOpen(true);
        }, 0);
    };

    return (
        <TableRow data-test="company-contact-row">
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
            <TableCell className="max-w-48">
                {audienceNames ? (
                    <span className="block truncate" title={audienceNames}>
                        {audienceNames}
                    </span>
                ) : (
                    <span className="text-muted-foreground">—</span>
                )}
            </TableCell>
            <TableCell className="text-muted-foreground">
                {contact.created_at
                    ? formatRelativeTime(contact.created_at)
                    : '—'}
            </TableCell>
        </TableRow>
    );
}
