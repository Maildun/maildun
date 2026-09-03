import AppLayoutTemplate from '@/layouts/app/app-sidebar-layout';
import type { BreadcrumbItem } from '@/types';

export default function AppLayout({
    breadcrumbs = [],
    fullscreen = false,
    children,
}: {
    breadcrumbs?: BreadcrumbItem[];
    fullscreen?: boolean;
    children: React.ReactNode;
}) {
    return (
        <AppLayoutTemplate breadcrumbs={breadcrumbs} fullscreen={fullscreen}>
            {children}
        </AppLayoutTemplate>
    );
}
