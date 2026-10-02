import { Link } from '@inertiajs/react';
import {
    Pagination,
    PaginationContent,
    PaginationEllipsis,
    PaginationItem,
    PaginationLink,
    PaginationNext,
    PaginationPrevious,
} from '@/components/ui/pagination';
import { cn } from '@/lib/utils';
import type {
    Paginated,
    PaginationLink as PaginationLinkData,
} from '@/types/audiences';

export function Paginator<T>({
    paginator,
    className,
    showSummary = false,
}: {
    paginator: Paginated<T>;
    className?: string;
    showSummary?: boolean;
}) {
    const previous = paginator.links[0];
    const next = paginator.links.at(-1);
    const pages = paginator.links.slice(1, -1);

    const navigation =
        paginator.last_page > 1 && previous && next ? (
            <Pagination
                className={
                    showSummary ? 'mx-0 w-auto min-w-0 justify-end' : className
                }
            >
                <PaginationContent
                    className={
                        showSummary ? 'flex-wrap justify-end' : undefined
                    }
                >
                    <PaginationItem>
                        <PaginationPrevious {...linkProps(previous)} />
                    </PaginationItem>
                    {pages.map((link, index) => (
                        <PaginationItem key={`${link.label}-${index}`}>
                            {link.label === '...' ? (
                                <PaginationEllipsis />
                            ) : (
                                <PaginationLink
                                    isActive={link.active}
                                    {...linkProps(link)}
                                >
                                    {link.label}
                                </PaginationLink>
                            )}
                        </PaginationItem>
                    ))}
                    <PaginationItem>
                        <PaginationNext {...linkProps(next)} />
                    </PaginationItem>
                </PaginationContent>
            </Pagination>
        ) : null;

    if (!showSummary) {
        return navigation;
    }

    return (
        <div
            className={cn(
                'flex w-full flex-wrap items-center justify-between gap-3 text-sm text-muted-foreground',
                className,
            )}
        >
            <span>
                Rows per page{' '}
                <span className="ml-2 font-medium text-foreground">
                    {paginator.per_page}
                </span>
            </span>
            <div className="flex max-w-full min-w-0 flex-wrap items-center justify-end gap-4">
                <span className="whitespace-nowrap tabular-nums">
                    {paginator.from ?? 0}–{paginator.to ?? 0} of{' '}
                    {paginator.total.toLocaleString()}
                </span>
                {navigation}
            </div>
        </div>
    );
}

function linkProps(link: PaginationLinkData) {
    return {
        disabled: !link.url,
        render: link.url ? <Link href={link.url} preserveScroll /> : undefined,
    };
}
