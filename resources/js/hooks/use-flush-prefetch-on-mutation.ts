import { router } from '@inertiajs/react';
import { useEffect } from 'react';

/**
 * Prefetched pages are cached for 30s, and Inertia keeps serving them after a
 * POST/PUT/PATCH/DELETE changes the data behind them — so a list reached
 * through a hovered `<Link prefetch>` would miss the row just added. Drop the
 * whole cache once any write finishes so the next visit fetches fresh props.
 */
export function useFlushPrefetchOnMutation(): void {
    useEffect(() => {
        return router.on('finish', (event) => {
            const { visit } = event.detail;

            if (visit.method !== 'get' && visit.completed) {
                router.flushAll();
            }
        });
    }, []);
}
