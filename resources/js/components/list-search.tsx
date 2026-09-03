import { Search01Icon } from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { useEffect, useRef } from 'react';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

const SEARCH_DEBOUNCE_MS = 400;

export function ListSearch({
    value,
    onSearch,
    placeholder,
    label,
    className,
}: {
    value: string;
    onSearch: (search: string) => void;
    placeholder: string;
    label?: string;
    className?: string;
}) {
    const searchTimeout = useRef<ReturnType<typeof setTimeout> | null>(null);

    useEffect(() => {
        return () => {
            if (searchTimeout.current) {
                clearTimeout(searchTimeout.current);
            }
        };
    }, []);

    return (
        <div className={cn('relative flex-1 sm:max-w-sm', className)}>
            <HugeiconsIcon
                icon={Search01Icon}
                className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
            />
            <Input
                defaultValue={value}
                onChange={(event) => {
                    const search = event.target.value;

                    if (searchTimeout.current) {
                        clearTimeout(searchTimeout.current);
                    }

                    searchTimeout.current = setTimeout(() => {
                        onSearch(search);
                    }, SEARCH_DEBOUNCE_MS);
                }}
                placeholder={placeholder}
                aria-label={label ?? placeholder}
                className="pl-8"
            />
        </div>
    );
}
