import { router } from '@inertiajs/react';
import { useState } from 'react';
import { useClearFiltersOnEscape } from '@/hooks/use-clear-filters-on-escape';

/**
 * Query-string filter state for a list page: visiting with merged filters,
 * clearing one or all of them, and the Esc shortcut behind Clear Filters.
 *
 * `empty` holds the reset value of every field that counts as a filter, so a
 * field left out of it (a sort, a tab that is not a filter) survives a clear.
 */
export function useListFilters<T extends Record<string, string>>({
    url,
    filters,
    empty,
    searchField = 'q',
}: {
    url: string;
    filters: T;
    empty: Partial<T>;
    searchField?: keyof T & string;
}) {
    const [searchKey, setSearchKey] = useState(0);

    const visit = (next: Partial<T>) => {
        router.get(
            url,
            { ...filters, ...next },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    };

    /** Clears specific fields, resetting the uncontrolled search input with it. */
    const clear = (next: Partial<T>) => {
        if (searchField in next) {
            setSearchKey((key) => key + 1);
        }

        visit(next);
    };

    const clearAll = () => {
        setSearchKey((key) => key + 1);
        visit(empty);
    };

    const hasActiveFilters = Object.keys(empty).some(
        (field) => filters[field] !== empty[field],
    );

    useClearFiltersOnEscape(hasActiveFilters, clearAll);

    return { visit, clear, clearAll, hasActiveFilters, searchKey };
}
