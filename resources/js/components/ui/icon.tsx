import type { IconSvgElement } from '@hugeicons/react';
import { HugeiconsIcon } from '@hugeicons/react';

interface IconProps {
    iconNode?: IconSvgElement | null;
    className?: string;
}

export function Icon({ iconNode, className }: IconProps) {
    if (!iconNode) {
        return null;
    }

    return <HugeiconsIcon icon={iconNode} className={className} />;
}
