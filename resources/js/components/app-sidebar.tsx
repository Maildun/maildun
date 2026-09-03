import {
    Alert02Icon,
    CleanIcon,
    Building06Icon,
    File01Icon,
    FolderLibraryIcon,
    MailAtSign02Icon,
    MailSend02Icon,
    MenuCircleIcon,
    NodeEditIcon,
    UserGroupIcon,
    UserIcon,
} from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { Link, usePage } from '@inertiajs/react';
import type { ComponentProps } from 'react';
import AppLogo from '@/components/app-logo';
import { GettingStartedChecklist } from '@/components/getting-started-checklist';
import { NavMain } from '@/components/nav-main';
import { NavSearch } from '@/components/nav-search';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarRail,
    SidebarTrigger,
    useSidebar,
} from '@/components/ui/sidebar';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import { index as audiences } from '@/routes/audiences';
import { index as automations } from '@/routes/automations';
import { index as companies } from '@/routes/companies';
import { index as contacts } from '@/routes/contacts';
import { index as templates } from '@/routes/email_templates';
import { index as emails } from '@/routes/emails';
import { index as listHygiene } from '@/routes/list_hygiene';
import { index as mediaLibrary } from '@/routes/media';
import { show as showSystemCheck } from '@/routes/system-check';
import { index as transactionalEmails } from '@/routes/transactional_emails';
import type { AppUpdateStatus, NavItem } from '@/types';

export function AppSidebar() {
    const page = usePage();
    const { appUpdate, currentTeam } = page.props;
    const dashboardUrl = currentTeam ? dashboard(currentTeam.slug) : '/';
    const audiencesUrl = currentTeam ? audiences(currentTeam.slug) : '/';
    const contactsUrl = currentTeam ? contacts(currentTeam.slug) : '/';
    const companiesUrl = currentTeam ? companies(currentTeam.slug) : '/';
    const listHygieneUrl = currentTeam ? listHygiene(currentTeam.slug) : '/';
    const emailsUrl = currentTeam ? emails(currentTeam.slug) : '/';
    const automationsUrl = currentTeam ? automations(currentTeam.slug) : '/';
    const transactionalUrl = currentTeam
        ? transactionalEmails(currentTeam.slug)
        : '/';
    const templatesUrl = currentTeam ? templates(currentTeam.slug) : '/';
    const mediaUrl = currentTeam ? mediaLibrary(currentTeam.slug) : '/';

    const overviewNavItems: NavItem[] = [
        {
            title: 'Dashboard',
            href: dashboardUrl,
            icon: MenuCircleIcon,
        },
    ];

    const audienceNavItems: NavItem[] = [
        {
            title: 'Contacts',
            href: contactsUrl,
            icon: UserIcon,
        },
        {
            title: 'Audiences',
            href: audiencesUrl,
            icon: UserGroupIcon,
        },
        {
            title: 'Companies',
            href: companiesUrl,
            icon: Building06Icon,
        },
        {
            title: 'List Hygiene',
            href: listHygieneUrl,
            icon: CleanIcon,
        },
    ];

    const campaignNavItems: NavItem[] = [
        {
            title: 'Campaigns',
            href: emailsUrl,
            icon: MailAtSign02Icon,
        },
        {
            title: 'Transactional',
            href: transactionalUrl,
            icon: MailSend02Icon,
        },
        {
            title: 'Automations',
            href: automationsUrl,
            icon: NodeEditIcon,
        },
        {
            title: 'Templates',
            href: templatesUrl,
            icon: File01Icon,
        },
        {
            title: 'Media',
            href: mediaUrl,
            icon: FolderLibraryIcon,
        },
    ];

    const mainNavItems = [
        ...overviewNavItems,
        ...audienceNavItems,
        ...campaignNavItems,
    ];

    return (
        <Sidebar collapsible="icon">
            <SidebarHeader>
                <SidebarHeaderBrand href={dashboardUrl} />
            </SidebarHeader>

            <SidebarContent className="gap-4">
                <NavSearch items={mainNavItems} />
                <NavMain items={overviewNavItems} label="Overview" />
                <NavMain items={audienceNavItems} label="Contacts" />
                <NavMain items={campaignNavItems} label="Manage campaign" />
            </SidebarContent>

            <SidebarFooter>
                <AppUpdateNotice update={appUpdate} />
                <GettingStartedChecklist />
                <NavUser />
            </SidebarFooter>
            <SidebarRail />
        </Sidebar>
    );
}

function AppUpdateNotice({ update }: { update: AppUpdateStatus | null }) {
    if (
        update?.status !== 'update_available' &&
        update?.status !== 'unsupported'
    ) {
        return null;
    }

    const title =
        update.status === 'unsupported'
            ? 'Update required'
            : `Update ${update.latest_version} available`;

    return (
        <SidebarMenu>
            <SidebarMenuItem className="after:hidden">
                <SidebarMenuButton
                    tooltip={title}
                    className="text-amber-700 hover:bg-amber-500/10 hover:text-amber-800 dark:text-amber-400 dark:hover:text-amber-300"
                    render={<Link href={showSystemCheck()} />}
                >
                    <HugeiconsIcon icon={Alert02Icon} />
                    <span>{title}</span>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    );
}

function SidebarHeaderBrand({
    href,
}: {
    href: ComponentProps<typeof Link>['href'];
}) {
    const { state } = useSidebar();
    const collapsed = state === 'collapsed';

    return (
        <div className="group/logo relative flex w-full items-center">
            <SidebarMenu className="min-w-0 flex-1">
                <SidebarMenuItem className="after:hidden">
                    <SidebarMenuButton
                        size="lg"
                        className={cn(
                            'w-auto min-w-0 hover:bg-transparent! active:bg-transparent! data-open:hover:bg-transparent! [&_svg]:size-5!',
                            collapsed &&
                                'transition-opacity group-focus-within/logo:opacity-0 group-hover/logo:opacity-0',
                        )}
                        render={<Link href={href} prefetch />}
                    >
                        <AppLogo showName={false} />
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
            <SidebarTrigger
                className={cn(
                    'text-sidebar-foreground/70 hover:bg-sidebar-accent hover:text-sidebar-accent-foreground',
                    collapsed
                        ? 'absolute inset-0 bg-sidebar opacity-0 transition-opacity group-focus-within/logo:opacity-100 group-hover/logo:opacity-100'
                        : 'ml-auto',
                )}
            />
        </div>
    );
}
