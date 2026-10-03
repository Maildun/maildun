import { Cancel01Icon } from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { AnimatePresence, motion, useReducedMotion } from 'motion/react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';

/**
 * Floating bulk-action bar pinned to the bottom of the content area while
 * table rows are selected. Render it after the table: the in-flow spacer keeps
 * the last rows and paginator reachable underneath the bar.
 */
export function SelectionActionBar({
    count,
    noun,
    onClear,
    children,
}: {
    count: number;
    noun: [singular: string, plural: string];
    onClear: () => void;
    children: ReactNode;
}) {
    const shouldReduceMotion = useReducedMotion();

    // Keep showing the last count while the bar animates out.
    const [displayedCount, setDisplayedCount] = useState(count);

    if (count > 0 && count !== displayedCount) {
        setDisplayedCount(count);
    }

    return (
        <>
            {count > 0 && <div aria-hidden className="h-12" />}
            <div className="pointer-events-none fixed inset-x-0 bottom-6 z-40 flex justify-center px-4 md:group-has-data-[collapsible=icon]/sidebar-wrapper:left-(--sidebar-width-icon) md:group-has-data-[state=expanded]/sidebar-wrapper:left-(--sidebar-width)">
                <AnimatePresence>
                    {count > 0 && (
                        <motion.div
                            role="toolbar"
                            aria-label="Bulk actions"
                            data-testid="selection-action-bar"
                            className="pointer-events-auto flex origin-bottom items-center gap-3 rounded-xl bg-background py-2 pr-2 pl-4 text-foreground shadow-lg ring-1 ring-foreground/10 inverted-surface"
                            initial={
                                shouldReduceMotion
                                    ? { opacity: 0 }
                                    : { opacity: 0, y: 24, scale: 0.7 }
                            }
                            animate={{
                                opacity: 1,
                                y: 0,
                                scale: 1,
                                transition: shouldReduceMotion
                                    ? { duration: 0.15 }
                                    : {
                                          type: 'spring',
                                          duration: 0.45,
                                          bounce: 0.35,
                                          opacity: { duration: 0.15 },
                                      },
                            }}
                            exit={
                                shouldReduceMotion
                                    ? {
                                          opacity: 0,
                                          transition: { duration: 0.15 },
                                      }
                                    : {
                                          opacity: 0,
                                          y: 24,
                                          scale: 0.7,
                                          transition: {
                                              duration: 0.2,
                                              ease: [0.4, 0, 1, 1],
                                          },
                                      }
                            }
                        >
                            <p className="text-sm font-medium whitespace-nowrap">
                                {displayedCount.toLocaleString()}{' '}
                                {displayedCount === 1 ? noun[0] : noun[1]}{' '}
                                selected
                            </p>
                            <div className="h-5 w-px bg-border" />
                            <div className="flex items-center gap-2">
                                {children}
                            </div>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon-sm"
                                onClick={onClear}
                            >
                                <HugeiconsIcon
                                    icon={Cancel01Icon}
                                    strokeWidth={2}
                                />
                                <span className="sr-only">Clear selection</span>
                            </Button>
                        </motion.div>
                    )}
                </AnimatePresence>
            </div>
        </>
    );
}
