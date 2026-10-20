import { useCallback, useEffect, useRef } from 'react';

/**
 * Returns a getter for a signal that aborts when the component unmounts, so
 * work it starts, such as polling for an upload, never outlives the page.
 */
export function useUnmountSignal(): () => AbortSignal {
    const controller = useRef<AbortController | null>(null);

    useEffect(() => {
        const current = new AbortController();
        controller.current = current;

        return () => current.abort();
    }, []);

    return useCallback(() => {
        controller.current ??= new AbortController();

        return controller.current.signal;
    }, []);
}
