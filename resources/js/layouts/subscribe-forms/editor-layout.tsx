import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { AppLayoutProps } from '@/types';

export default function SubscribeFormEditorLayout({
    breadcrumbs = [],
    children,
}: AppLayoutProps) {
    return (
        <AppShell variant="sidebar">
            <AppSidebar />
            <AppContent variant="sidebar" className="min-h-0 overflow-hidden">
                <div className="flex min-h-0 flex-1 flex-col">
                    <header className="flex h-12 shrink-0 items-center gap-2 border-b">
                        <div className="flex min-w-0 items-center gap-2 px-4">
                            <SidebarTrigger className="-ml-1 md:hidden" />
                            <Breadcrumbs breadcrumbs={breadcrumbs} />
                        </div>
                    </header>
                    <div className="flex min-h-0 flex-1 flex-col">
                        {children}
                    </div>
                </div>
            </AppContent>
        </AppShell>
    );
}
