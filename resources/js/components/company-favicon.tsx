import { Building06Icon } from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { useState } from 'react';

/**
 * Company favicon that falls back to the building icon when the image is missing
 * or fails to load (for example a Google favicon that has since disappeared).
 */
export function CompanyFavicon({
    src,
    className,
    iconClassName,
}: {
    src: string | null;
    className: string;
    iconClassName: string;
}) {
    const [failedSrc, setFailedSrc] = useState<string | null>(null);

    if (!src || failedSrc === src) {
        return (
            <HugeiconsIcon icon={Building06Icon} className={iconClassName} />
        );
    }

    return (
        <img
            src={src}
            alt=""
            className={className}
            onError={() => setFailedSrc(src)}
        />
    );
}
