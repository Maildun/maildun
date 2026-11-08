import { createInertiaApp } from '@inertiajs/react';
import type { ComponentType, ReactNode } from 'react';
import { AppProviders } from '@/components/app-providers';
import { initializeTheme } from '@/hooks/use-appearance';

const appName = import.meta.env.VITE_APP_NAME || 'Maildun';

type PageLayout = 'app' | 'auth' | 'settings';

type LayoutComponent = ComponentType<{ children: ReactNode }>;

/**
 * Layouts load alongside the first page that uses them instead of with this
 * entry, so public pages (the hosted subscribe form, unsubscribe, about) never
 * download the app sidebar, command palette, or settings navigation.
 */
const layoutModules: Record<
    PageLayout,
    () => Promise<{ default: LayoutComponent }>
> = {
    app: () => import('@/layouts/app-layout'),
    auth: () => import('@/layouts/auth-layout'),
    settings: () => import('@/layouts/settings/layout'),
};

const loadedLayouts = new Map<PageLayout, LayoutComponent>();

/**
 * A page's own `.layout = null` does not opt out: Inertia falls back to this
 * default for a nullish page layout, so layout-less pages are listed here.
 */
function pageLayout(name: string): PageLayout | null {
    switch (true) {
        case name === 'about':
        case name === 'automations/edit':
        case name === 'subscribe-forms/confirmed':
        case name === 'subscribe-forms/edit':
        case name === 'subscribe-forms/public':
        case name === 'unsubscribe/show':
        case name === 'teams/create':
            return null;
        case name.startsWith('audiences/'):
        case name.startsWith('email-templates/'):
        case name.startsWith('emails/'):
        case name.startsWith('segments/'):
            return 'app';
        case name.startsWith('auth/'):
            return 'auth';
        case name.startsWith('settings/'):
        case name.startsWith('teams/'):
            return 'settings';
        default:
            return 'app';
    }
}

/**
 * Declare no components in this file. A component here makes the entry a Fast
 * Refresh boundary, so a hot update to any of its dependencies re-executes this
 * module, `createInertiaApp()` runs again, and a second React root mounts on
 * `#app` — React then warns about a duplicate `createRoot` and the remount
 * fails to hydrate. Without a boundary, Vite falls back to a full reload.
 * `createInertiaApp()` must also stay a top-level call: @inertiajs/vite rewrites
 * that statement to build the SSR entry.
 *
 * `resolve` waits for the page's layout as well as the page, so `layout` can
 * hand it back synchronously on the client and during SSR.
 */
createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: async (name) => {
        const pages = import.meta.glob<{ default: ComponentType }>(
            './pages/**/*.tsx',
        );
        const layout = pageLayout(name);
        const [page] = await Promise.all([
            pages[`./pages/${name}.tsx`]?.(),
            layout && !loadedLayouts.has(layout)
                ? layoutModules[layout]().then((module) => {
                      loadedLayouts.set(layout, module.default);
                  })
                : undefined,
        ]);

        if (!page) {
            throw new Error(`Page not found: ${name}`);
        }

        return page.default;
    },
    layout: (name) => {
        const layout = pageLayout(name);

        return layout ? (loadedLayouts.get(layout) ?? null) : null;
    },
    strictMode: true,
    withApp(app) {
        return <AppProviders>{app}</AppProviders>;
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();
