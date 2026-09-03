import { createInertiaApp } from '@inertiajs/react';
import { AppProviders } from '@/components/app-providers';
import { initializeTheme } from '@/hooks/use-appearance';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import SettingsLayout from '@/layouts/settings/layout';

const appName = import.meta.env.VITE_APP_NAME || 'Maildun';

/**
 * Declare no components in this file. A component here makes the entry a Fast
 * Refresh boundary, so a hot update to any of its dependencies re-executes this
 * module, `createInertiaApp()` runs again, and a second React root mounts on
 * `#app` — React then warns about a duplicate `createRoot` and the remount
 * fails to hydrate. Without a boundary, Vite falls back to a full reload.
 * `createInertiaApp()` must also stay a top-level call: @inertiajs/vite rewrites
 * that statement to build the SSR entry.
 */
createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'automations/edit':
            case name === 'subscribe-forms/edit':
            case name === 'subscribe-forms/public':
            case name === 'unsubscribe/show':
            case name === 'teams/create':
                return null;
            case name.startsWith('audiences/'):
            case name.startsWith('email-templates/'):
            case name.startsWith('emails/'):
            case name.startsWith('segments/'):
                return AppLayout;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
            case name.startsWith('teams/'):
                return SettingsLayout;
            default:
                return AppLayout;
        }
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
