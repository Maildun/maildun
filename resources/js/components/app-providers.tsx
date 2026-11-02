import type { ReactNode } from 'react';
import { Toaster } from '@/components/ui/toast';
import { TooltipProvider } from '@/components/ui/tooltip';
import { useFlashToast } from '@/hooks/use-flash-toast';
import { useFlushPrefetchOnMutation } from '@/hooks/use-flush-prefetch-on-mutation';

export function AppProviders({ children }: { children: ReactNode }) {
    useFlashToast();
    useFlushPrefetchOnMutation();

    return (
        <TooltipProvider delay={0}>
            {children}
            <Toaster />
        </TooltipProvider>
    );
}
