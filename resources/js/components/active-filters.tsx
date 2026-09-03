import { Cancel01Icon } from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { Button } from '@/components/ui/button';
import { Kbd } from '@/components/ui/kbd';
import { Separator } from '@/components/ui/separator';
import { cn } from '@/lib/utils';

export type ActiveFilterItem = {
    key: string;
    field: string;
    value: string;
    onClear: () => void;
};

export function ActiveFilter({
    field,
    value,
    onClear,
}: Omit<ActiveFilterItem, 'key'>) {
    return (
        <div className="inline-flex h-9 items-stretch overflow-hidden rounded-lg border bg-muted text-sm">
            <span className="flex items-center px-2.5 text-muted-foreground">
                {field}
            </span>
            <Separator orientation="vertical" />
            <span className="flex items-center px-2.5 text-muted-foreground">
                is
            </span>
            <Separator orientation="vertical" />
            <span className="flex max-w-48 min-w-0 items-center px-2.5 font-medium">
                <span className="truncate">{value}</span>
            </span>
            <Separator orientation="vertical" />
            <button
                type="button"
                aria-label={`Clear ${field} filter`}
                className="flex items-center px-2 text-muted-foreground hover:bg-foreground/10"
                onClick={onClear}
            >
                <HugeiconsIcon icon={Cancel01Icon} className="size-3.5" />
            </button>
        </div>
    );
}

export function ActiveFilters({
    filters,
    onClearAll,
    clearLabel = 'Clear Filters',
    clearTestId,
    className,
}: {
    filters: ActiveFilterItem[];
    onClearAll?: () => void;
    clearLabel?: string;
    clearTestId?: string;
    className?: string;
}) {
    if (filters.length === 0) {
        return null;
    }

    return (
        <div
            className={cn(
                'flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between',
                className,
            )}
        >
            <div className="flex flex-wrap items-center gap-2">
                {filters.map((filter) => (
                    <ActiveFilter
                        key={filter.key}
                        field={filter.field}
                        value={filter.value}
                        onClear={filter.onClear}
                    />
                ))}
            </div>
            {onClearAll ? (
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    data-test={clearTestId}
                    onClick={onClearAll}
                >
                    {clearLabel}
                    <Kbd>Esc</Kbd>
                </Button>
            ) : null}
        </div>
    );
}
