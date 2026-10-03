import { usePage } from '@inertiajs/react';
import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { AttributionBadge } from '@/components/attribution-badge';
import { DeliveryPausedBanner } from '@/components/delivery-paused-banner';
import { AudienceSettingsSidebar } from '@/layouts/audiences/settings-layout';
import { cn } from '@/lib/utils';
import type { AppLayoutProps } from '@/types';
import type { Audience } from '@/types/audiences';

export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
    fullscreen = false,
}: AppLayoutProps) {
    const page = usePage<{
        audience?: Pick<Audience, 'uuid' | 'name' | 'avatar'>;
    }>();
    const settingsAudience =
        !fullscreen &&
        (page.component === 'audiences/edit' ||
            page.component.startsWith('audiences/settings/'))
            ? page.props.audience
            : undefined;

    return (
        <AppShell variant="sidebar">
            <div
                className={fullscreen ? 'hidden' : 'contents'}
                aria-hidden={fullscreen}
            >
                <AppSidebar />
            </div>
            <AppContent
                variant="sidebar"
                className={cn(
                    'min-h-0 min-w-0 overflow-x-hidden',
                    settingsAudience && 'h-dvh overflow-hidden',
                    fullscreen &&
                        'fixed inset-0 z-50 h-dvh overflow-hidden bg-background',
                )}
            >
                <div
                    className={cn(
                        'flex min-h-0 min-w-0 flex-1 flex-col',
                        settingsAudience && 'md:flex-row',
                    )}
                >
                    {settingsAudience && (
                        <AudienceSettingsSidebar audience={settingsAudience} />
                    )}
                    <div
                        className={cn(
                            'mx-auto flex min-h-0 w-full max-w-7xl min-w-0 flex-1 flex-col',
                            fullscreen && 'h-dvh max-w-none',
                            settingsAudience && 'max-w-none overflow-y-auto',
                        )}
                    >
                        <div
                            className={
                                fullscreen || settingsAudience
                                    ? 'hidden'
                                    : 'contents'
                            }
                        >
                            <AppSidebarHeader breadcrumbs={breadcrumbs} />
                        </div>
                        <div
                            className={cn(
                                'flex min-h-0 min-w-0 flex-1 flex-col p-10',
                                fullscreen && 'p-0',
                                settingsAudience &&
                                    'mx-auto min-h-auto w-full max-w-4xl p-6 lg:p-10',
                            )}
                        >
                            {!fullscreen && <DeliveryPausedBanner />}
                            {children}
                        </div>
                        <AttributionBadge
                            className={cn(
                                'px-10 pb-6 text-[11px] opacity-40 transition-opacity hover:opacity-100',
                                fullscreen && 'hidden',
                            )}
                        />
                    </div>
                </div>
            </AppContent>
        </AppShell>
    );
}
