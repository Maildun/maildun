import { useCallback, useLayoutEffect, useRef, useState } from 'react';
import type { HTMLAttributes, ReactNode } from 'react';
import { cn } from '@/lib/utils';

type Indicator = {
    left: number;
    width: number;
};

export const slidingUnderlineInactiveClassName =
    "after:absolute after:inset-x-0 after:bottom-0 after:h-1 after:translate-y-full after:rounded-t after:bg-border after:transition-transform after:duration-300 after:ease-out after:content-[''] hover:after:translate-y-0 focus-visible:after:translate-y-0 motion-reduce:after:transition-none";

export function SlidingUnderlineList({
    activeKey,
    className,
    children,
    ...props
}: {
    activeKey: string;
    children: ReactNode;
} & HTMLAttributes<HTMLDivElement>) {
    const listRef = useRef<HTMLDivElement>(null);
    const [indicator, setIndicator] = useState<Indicator | null>(null);

    const updateIndicator = useCallback(() => {
        const list = listRef.current;
        const activeTrigger = list?.querySelector<HTMLElement>(
            ':scope > [data-active="true"]',
        );

        if (!list || !activeTrigger) {
            return;
        }

        const listRect = list.getBoundingClientRect();
        const triggerRect = activeTrigger.getBoundingClientRect();
        const nextIndicator = {
            left: Math.round(
                triggerRect.left - listRect.left + list.scrollLeft,
            ),
            width: Math.round(triggerRect.width),
        };

        setIndicator((currentIndicator) =>
            currentIndicator?.left === nextIndicator.left &&
            currentIndicator.width === nextIndicator.width
                ? currentIndicator
                : nextIndicator,
        );
    }, []);

    useLayoutEffect(() => {
        updateIndicator();

        const list = listRef.current;

        if (!list) {
            return;
        }

        const resizeObserver = new ResizeObserver(updateIndicator);
        resizeObserver.observe(list);

        for (const child of list.children) {
            resizeObserver.observe(child);
        }

        list.addEventListener('scroll', updateIndicator, { passive: true });

        return () => {
            resizeObserver.disconnect();
            list.removeEventListener('scroll', updateIndicator);
        };
    }, [activeKey, updateIndicator]);

    return (
        <div
            ref={listRef}
            data-slot="sliding-underline-list"
            className={cn(
                'relative flex overflow-x-auto overflow-y-hidden before:pointer-events-none before:absolute before:inset-x-0 before:bottom-0 before:h-px before:bg-border',
                className,
            )}
            {...props}
        >
            {indicator && (
                <span
                    aria-hidden="true"
                    className="pointer-events-none absolute bottom-0 left-0 h-1 rounded-t bg-foreground transition-[transform,width,opacity] duration-300 ease-out motion-safe:animate-in motion-safe:duration-300 motion-safe:fade-in-0 motion-safe:slide-in-from-bottom-1 motion-reduce:transition-none"
                    style={{
                        transform: `translateX(${indicator.left}px)`,
                        width: `${indicator.width}px`,
                    }}
                />
            )}
            {children}
        </div>
    );
}
