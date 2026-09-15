import '@inertiajs/core';
import type { AppUpdateStatus } from '@/types/app-update';
import type { Auth } from '@/types/auth';
import type { RecentCampaign } from '@/types/emails';
import type { OnboardingChecklist } from '@/types/onboarding';
import type { Team } from '@/types/teams';

declare module 'react' {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            attribution: { sourceUrl: string };
            auth: Auth;
            registrationOpen: boolean;
            sidebarOpen: boolean;
            currentTeam: Team | null;
            teams: Team[];
            appUpdate: AppUpdateStatus | null;
            onboarding: OnboardingChecklist | null;
            recentCampaigns: RecentCampaign[];
            [key: string]: unknown;
        };
    }
}
