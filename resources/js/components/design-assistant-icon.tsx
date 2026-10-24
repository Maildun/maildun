import { AiChat02Icon } from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import AppLogoIcon from '@/components/app-logo-icon';
import { cn } from '@/lib/utils';

type Props = {
    loading?: boolean;
    className?: string;
    'data-icon'?: string;
};

/**
 * The design assistant mark: an AI chat bubble, swapped for the turning
 * Maildun mark while a turn is running. Decorative — the control next to it
 * has the name.
 */
export function DesignAssistantIcon({
    loading = false,
    className,
    ...props
}: Props) {
    if (loading) {
        return (
            <AppLogoIcon
                animate="think"
                aria-hidden
                className={cn('size-4', className)}
                {...props}
            />
        );
    }

    return (
        <HugeiconsIcon
            icon={AiChat02Icon}
            aria-hidden
            className={cn('shrink-0', className)}
            {...props}
        />
    );
}
