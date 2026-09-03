import { useEffect } from 'react';

export function useClearFiltersOnEscape(
    enabled: boolean,
    onClear: () => void,
): void {
    useEffect(() => {
        if (!enabled) {
            return;
        }

        const onKeyDown = (event: KeyboardEvent) => {
            if (event.key !== 'Escape') {
                return;
            }

            if (
                document.querySelector('[role="dialog"]') ||
                document.querySelector('[data-slot="dropdown-menu-content"]')
            ) {
                return;
            }

            event.preventDefault();
            onClear();
        };

        window.addEventListener('keydown', onKeyDown);

        return () => window.removeEventListener('keydown', onKeyDown);
    }, [enabled, onClear]);
}
