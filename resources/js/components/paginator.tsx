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
import type {
    Paginated,
    PaginationLink as PaginationLinkData,
} from '@/types/audiences';

export function Paginator<T>({ paginator }: { paginator: Paginated<T> }) {
    if (paginator.last_page <= 1) {
        return null;
    }

    const previous = paginator.links[0];
    const next = paginator.links.at(-1);
    const pages = paginator.links.slice(1, -1);

    if (!previous || !next) {
        return null;
    }

    return (
        <Pagination>
            <PaginationContent>
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
    );
}

function linkProps(link: PaginationLinkData) {
    return {
        disabled: !link.url,
        render: link.url ? <Link href={link.url} preserveScroll /> : undefined,
    };
}
