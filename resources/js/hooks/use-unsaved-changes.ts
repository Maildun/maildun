import { router } from '@inertiajs/react';
import { useEffect } from 'react';

const CONFIRM_MESSAGE = 'You have unsaved changes. Leave this page?';

export function useUnsavedChanges(isDirty: boolean): void {
    useEffect(() => {
        if (!isDirty) {
            return;
        }

        const remove = router.on('before', (event) => {
            const visit = event.detail.visit;

            if (visit.prefetch || visit.method !== 'get') {
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
