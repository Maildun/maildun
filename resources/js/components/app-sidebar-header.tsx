import { Breadcrumbs } from '@/components/breadcrumbs';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    const hasBreadcrumbs = breadcrumbs.length > 1;

    return (
        <header
            className={cn(
                'flex h-12 shrink-0 items-center gap-2 border-b transition-[width,height] ease-linear',
                !hasBreadcrumbs && 'md:hidden',
            )}
        >
            <div className="flex min-w-0 items-center gap-2 px-4 md:px-10">
                <SidebarTrigger className="-ml-1 md:hidden" />
                {hasBreadcrumbs && <Breadcrumbs breadcrumbs={breadcrumbs} />}
            </div>
        </header>
    );
}
