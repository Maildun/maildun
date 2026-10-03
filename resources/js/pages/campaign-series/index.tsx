import {
    Add01Icon,
    FoldersIcon,
    Target02Icon,
} from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { Head, Link, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { CampaignSeriesDialog } from '@/components/campaign-series-dialog';
import { ListSearch } from '@/components/list-search';
import { Paginator } from '@/components/paginator';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Empty,
    EmptyContent,
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
import { index as seriesIndex, show } from '@/routes/campaign_series';
import type { CampaignSeriesGoalOption, CampaignSeriesSummary } from '@/types';
import type { Paginated } from '@/types/audiences';

type Props = {
    series: Paginated<CampaignSeriesSummary>;
    filters: { q: string };
    goals: CampaignSeriesGoalOption[];
    canManage: boolean;
};

export default function CampaignSeriesIndex({
    series,
    filters,
    goals,
    canManage,
}: Props) {
    const { currentTeam } = usePage().props;
    const [createOpen, setCreateOpen] = useState(false);
    const { visit, clearAll, hasActiveFilters, searchKey } = useListFilters({
        url: currentTeam ? seriesIndex.url(currentTeam.slug) : '',
        filters,
        empty: { q: '' },
    });

    if (!currentTeam) {
        return null;
    }

    return (
        <>
            <Head title="Campaign series" />
            <div className="flex flex-1 flex-col gap-6">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                    <div className="flex flex-col gap-1">
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Campaign series
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Group sales campaigns, track the shared goal, and
                            compare every send side by side.
                        </p>
                    </div>
                    {canManage && (
                        <Button onClick={() => setCreateOpen(true)}>
                            <HugeiconsIcon
                                icon={Add01Icon}
                                data-icon="inline-start"
                            />
                            New series
                        </Button>
                    )}
                </div>

                {(series.data.length > 0 || hasActiveFilters) && (
                    <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <ListSearch
                            key={searchKey}
                            value={filters.q}
                            onSearch={(q) => visit({ q })}
                            placeholder="Search campaign series"
                            className="sm:min-w-72"
                        />
                        {hasActiveFilters && (
                            <Button variant="ghost" onClick={clearAll}>
                                Clear search
                            </Button>
                        )}
                    </div>
                )}

                {series.data.length === 0 ? (
                    <Empty>
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <HugeiconsIcon icon={FoldersIcon} />
                            </EmptyMedia>
                            <EmptyTitle>
                                {hasActiveFilters
                                    ? 'No matching campaign series'
                                    : 'No campaign series yet'}
                            </EmptyTitle>
                            <EmptyDescription>
                                {hasActiveFilters
                                    ? 'Try another search.'
                                    : 'Create a series for a sales goal, then add existing campaigns or compose new ones inside it.'}
                            </EmptyDescription>
                        </EmptyHeader>
                        {canManage && !hasActiveFilters && (
                            <EmptyContent>
                                <Button onClick={() => setCreateOpen(true)}>
                                    Create campaign series
                                </Button>
                            </EmptyContent>
                        )}
                    </Empty>
                ) : (
                    <Table
                        footer={<Paginator paginator={series} showSummary />}
                    >
                        <TableHeader>
                            <TableRow>
                                <TableHead>Series</TableHead>
                                <TableHead>Sales goal</TableHead>
                                <TableHead>Campaigns</TableHead>
                                <TableHead>Sent</TableHead>
                                <TableHead>Updated</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {series.data.map((item) => (
                                <TableRow
                                    key={item.uuid}
                                    data-test="campaign-series-row"
                                >
                                    <TableCell>
                                        <div className="flex min-w-0 flex-col gap-1">
                                            <Link
                                                href={show([
                                                    currentTeam.slug,
                                                    item.uuid,
                                                ])}
                                                prefetch
                                                className="font-medium underline-offset-4 hover:underline"
                                            >
                                                {item.name}
                                            </Link>
                                            {(item.objective ||
                                                item.description) && (
                                                <span className="max-w-xl truncate text-muted-foreground">
                                                    {item.objective ??
                                                        item.description}
                                                </span>
                                            )}
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <Badge variant="secondary">
                                            <HugeiconsIcon
                                                icon={Target02Icon}
                                            />
                                            {item.goal_label}
                                        </Badge>
                                    </TableCell>
                                    <TableCell className="tabular-nums">
                                        {item.campaigns_count.toLocaleString()}
                                    </TableCell>
                                    <TableCell className="tabular-nums">
                                        {item.sent_campaigns_count.toLocaleString()}
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {item.updated_at
                                            ? formatRelativeTime(
                                                  item.updated_at,
                                              )
                                            : '—'}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
                {series.data.length === 0 && <Paginator paginator={series} />}
            </div>

            {canManage && (
                <CampaignSeriesDialog
                    teamSlug={currentTeam.slug}
                    goals={goals}
                    open={createOpen}
                    onOpenChange={setCreateOpen}
                />
            )}
        </>
    );
}
