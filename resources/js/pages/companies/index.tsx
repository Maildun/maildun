import {
    ArrowDown01Icon,
    Building06Icon,
    UserIcon,
} from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { Head, Link, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { CompanyDialog } from '@/components/company-dialog';
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
import { index, show } from '@/routes/companies';
import type { Paginated } from '@/types/audiences';
import type { CompanyIndexFilters, CompanySummary } from '@/types/companies';

type Props = {
    companies: Paginated<CompanySummary>;
    filters: CompanyIndexFilters;
    canManage: boolean;
};

const SORT_LABELS = {
    newest: 'Newest',
    oldest: 'Oldest',
    name: 'Name',
    contacts: 'Most contacts',
} as const;

export default function CompaniesIndex({
    companies,
    filters,
    canManage,
}: Props) {
    const { currentTeam } = usePage().props;
    const [createOpen, setCreateOpen] = useState(false);
    const { visit, searchKey } = useListFilters({
        url: currentTeam ? index.url(currentTeam.slug) : '',
        filters,
        empty: { search: '' },
        searchField: 'search',
    });

    if (!currentTeam) {
        return null;
    }

    return (
        <>
            <Head title="Companies" />
            <div className="flex flex-1 flex-col gap-6">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                    <div className="flex flex-col gap-1">
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Companies
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Associate contacts automatically using one or more
                            company domains.
                        </p>
                    </div>
                    {canManage ? (
                        <CompanyDialog
                            teamSlug={currentTeam.slug}
                            open={createOpen}
                            onOpenChange={setCreateOpen}
                        />
                    ) : null}
                </div>
                <div className="flex flex-col gap-2 sm:flex-row sm:justify-end">
                    <ListSearch
                        key={searchKey}
                        value={filters.search}
                        onSearch={(search) => visit({ search })}
                        placeholder="Search companies or domains"
                        className="sm:min-w-72"
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
                                <DropdownMenuLabel>Sort by</DropdownMenuLabel>
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
                {companies.total === 0 ? (
                    <Empty>
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <HugeiconsIcon icon={Building06Icon} />
                            </EmptyMedia>
                            <EmptyTitle>No companies yet</EmptyTitle>
                            <EmptyDescription>
                                Add a company domain to start associating
                                contacts automatically.
                            </EmptyDescription>
                        </EmptyHeader>
                    </Empty>
                ) : companies.data.length === 0 ? (
                    <Empty>
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <HugeiconsIcon icon={Building06Icon} />
                            </EmptyMedia>
                            <EmptyTitle>No matching companies</EmptyTitle>
                            <EmptyDescription>
                                Try another search.
                            </EmptyDescription>
                        </EmptyHeader>
                    </Empty>
                ) : (
                    <>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Company</TableHead>
                                    <TableHead>Domains</TableHead>
                                    <TableHead>Contacts</TableHead>
                                    <TableHead>Created</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {companies.data.map((company) => (
                                    <TableRow key={company.uuid}>
                                        <TableCell>
                                            <Link
                                                href={show([
                                                    currentTeam.slug,
                                                    company.uuid,
                                                ])}
                                                className="flex items-center gap-2 font-medium hover:underline"
                                            >
                                                {company.favicon ? (
                                                    <img
                                                        src={company.favicon}
                                                        alt=""
                                                        className="size-4 rounded-sm bg-white object-contain p-0.5"
                                                    />
                                                ) : (
                                                    <HugeiconsIcon
                                                        icon={Building06Icon}
                                                        className="size-4 text-muted-foreground"
                                                    />
                                                )}
                                                {company.name}
                                            </Link>
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex flex-wrap gap-1">
                                                {company.domains.map(
                                                    (domain) => (
                                                        <Badge
                                                            key={domain}
                                                            variant="outline"
                                                        >
                                                            {domain}
                                                        </Badge>
                                                    ),
                                                )}
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <span className="inline-flex items-center gap-1">
                                                <HugeiconsIcon
                                                    icon={UserIcon}
                                                    className="size-4 text-muted-foreground"
                                                />
                                                {company.contacts_count}
                                            </span>
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {company.created_at
                                                ? formatRelativeTime(
                                                      company.created_at,
                                                  )
                                                : '—'}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                        <Paginator paginator={companies} />
                    </>
                )}
            </div>
        </>
    );
}
