import { Globe02Icon } from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';

import { countryFlagUrl } from '@/lib/country-flags';
import { cn } from '@/lib/utils';

export function CountryFlag({
    code,
    label,
    className,
}: {
    code: string;
    label?: string;
    className?: string;
}) {
    const flagUrl = countryFlagUrl(code);

    if (!flagUrl) {
        return (
            <HugeiconsIcon
                icon={Globe02Icon}
                className={cn(
                    'size-4 shrink-0 text-muted-foreground',
                    className,
                )}
                aria-hidden="true"
            />
        );
    }

    return (
        <img
            src={flagUrl}
            alt={label ? `${label} flag` : ''}
            aria-hidden={label ? undefined : true}
            loading="lazy"
            decoding="async"
            width={20}
            height={15}
            className={cn(
                'h-[0.9375rem] w-5 shrink-0 rounded-xs object-cover ring-1 ring-foreground/10',
                className,
            )}
        />
    );
}
