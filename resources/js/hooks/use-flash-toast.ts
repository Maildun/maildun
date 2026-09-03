import { router } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from '@/components/ui/toast';
import type { FlashToast } from '@/types/ui';

export function useFlashToast(): void {
    useEffect(() => {
        return router.on('flash', (event) => {
            const flash = (event as CustomEvent).detail?.flash;
            const data = flash?.toast as FlashToast | undefined;

            if (!data) {
                return;
            }

            // No `id`: Base UI's addToast dedupes on id and updates the existing
            // toast in place, so reusing the message would collapse repeated
            // actions (deleting subscribers one by one) into a single toast
            // instead of stacking them.
            toast.add({
                type: data.type,
                title: data.message,
            });
        });
    }, []);
}
