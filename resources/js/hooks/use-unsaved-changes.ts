import type { PendingVisit } from '@inertiajs/core';
import { router } from '@inertiajs/react';
import { useEffect } from 'react';

const CONFIRM_MESSAGE = 'You have unsaved changes. Leave this page?';

export function shouldConfirmUnsavedVisit(
    visit: Pick<
        PendingVisit,
        'url' | 'method' | 'prefetch' | 'only' | 'preserveState'
    >,
    currentUrl: string,
): boolean {
    if (visit.prefetch || visit.method !== 'get') {
        return false;
    }

    return !(
        visit.url.href === currentUrl &&
        visit.only.length > 0 &&
        visit.preserveState === true
    );
}

export function useUnsavedChanges(isDirty: boolean): void {
    useEffect(() => {
        if (!isDirty) {
            return;
        }

        const remove = router.on('before', (event) => {
            const visit = event.detail.visit;

            if (!shouldConfirmUnsavedVisit(visit, window.location.href)) {
                return;
            }

            if (!window.confirm(CONFIRM_MESSAGE)) {
                event.preventDefault();

                return false;
            }
        });

        const onBeforeUnload = (event: BeforeUnloadEvent) => {
            event.preventDefault();
            event.returnValue = '';
        };

        window.addEventListener('beforeunload', onBeforeUnload);

        return () => {
            remove();
            window.removeEventListener('beforeunload', onBeforeUnload);
        };
    }, [isDirty]);
}

export function UnsavedChangesGuard({ isDirty }: { isDirty: boolean }) {
    useUnsavedChanges(isDirty);

    return null;
}
