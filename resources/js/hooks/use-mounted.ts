import { useSyncExternalStore } from 'react';

function subscribe() {
    return () => {};
}

function getSnapshot(): boolean {
    return true;
}

function getServerSnapshot(): boolean {
    return false;
}

export function useMounted(): boolean {
    return useSyncExternalStore(subscribe, getSnapshot, getServerSnapshot);
}
