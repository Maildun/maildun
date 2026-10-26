import { useEffect } from 'react';
import type { RefObject } from 'react';

/**
 * The editor unmounts its proposal bar the moment a proposal is accepted or
 * rejected, so there's nothing left to animate. This puts the removed bar back
 * where it was, inert, plays the exit animation on it and then drops it.
 * React has let go of the node by then, so it never touches it again.
 */
export function useProposalExit(
    frame: RefObject<HTMLElement | null>,
    mounted: boolean,
) {
    useEffect(() => {
        const root = frame.current;

        if (
            !root ||
            window.matchMedia?.('(prefers-reduced-motion: reduce)').matches
        ) {
            return;
        }

        // Every bar handled once, so dropping it at the end isn't caught as another removal.
        const handled = new WeakSet<HTMLElement>();
        const leaving = new Set<HTMLElement>();
        const observer = new MutationObserver((records) => {
            for (const record of records) {
                for (const node of record.removedNodes) {
                    if (
                        !(node instanceof HTMLElement) ||
                        node.dataset.slot !== 'proposal' ||
                        handled.has(node) ||
                        !record.target.isConnected
                    ) {
                        continue;
                    }

                    handled.add(node);
                    leaving.add(node);
                    node.inert = true;
                    node.setAttribute('aria-hidden', 'true');
                    node.removeAttribute('role');
                    node.classList.remove('motion-safe:animate-proposal-in');
                    node.classList.add(
                        'pointer-events-none',
                        'motion-safe:animate-proposal-out',
                    );
                    const next =
                        record.nextSibling?.parentNode === record.target
                            ? record.nextSibling
                            : null;
                    record.target.insertBefore(node, next);

                    const drop = () => {
                        leaving.delete(node);
                        node.remove();
                    };
                    node.addEventListener('animationend', drop, {
                        once: true,
                    });
                    // In case the animation never runs (hidden tab, CSS not loaded).
                    window.setTimeout(drop, 600);
                }
            }
        });

        observer.observe(root, { childList: true, subtree: true });

        return () => {
            observer.disconnect();
            leaving.forEach((node) => node.remove());
        };
    }, [frame, mounted]);
}
