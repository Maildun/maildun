import { router } from '@inertiajs/react';
import { useEffect } from 'react';

/**
 * Page props are a snapshot from the last visit, so rows added from another
 * tab, the API, a public form or a queued job stay invisible until the next
 * navigation. Partially reload the given props whenever the tab becomes
 * visible again so a manager coming back to a list sees what changed.
 */
export function useReloadOnFocus(only: string[]): void {
    const onlyKey = only.join(',');

    useEffect(() => {
        const reload = () => {
            if (document.visibilityState === 'visible') {
                router.reload({ only: onlyKey.split(',') });
            }
        };

        document.addEventListener('visibilitychange', reload);

        return () => document.removeEventListener('visibilitychange', reload);
    }, [onlyKey]);
}
