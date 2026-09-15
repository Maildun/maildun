import {
    ArrowLeft01Icon,
    DashboardSquareSettingIcon,
    Key01Icon,
    LockPasswordIcon,
    MailAtSign02Icon,
    MailEdit02Icon,
    MailSend02Icon,
    NewOfficeIcon,
    Search01Icon,
    ShieldCheckIcon,
    SunMoon as SunMoonIcon,
    TagsIcon,
    UserAdd01Icon,
    UserIcon,
} from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { Link, usePage } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import { useMemo, useState } from 'react';
import { NavUser } from '@/components/nav-user';
import { Button } from '@/components/ui/button';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupContent,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarInput,
    SidebarInset,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarProvider,
    SidebarRail,
    SidebarTrigger,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { toUrl } from '@/lib/utils';
import { dashboard } from '@/routes';
import { edit as editAppearance } from '@/routes/appearance';
import { edit } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import { show as showSystemCheck } from '@/routes/system-check';
import { index as tagsIndex } from '@/routes/tags';
import { edit as editTeam, index as teams } from '@/routes/teams';
import { index as teamApiSettings } from '@/routes/teams/api';
import { edit as teamEmailSettings } from '@/routes/teams/email';
import { edit as teamEmailProviderSettings } from '@/routes/teams/email-provider';
import { index as teamMembers } from '@/routes/teams/members';
import { index as teamRoles } from '@/routes/teams/roles';
import { edit as teamSenderSettings } from '@/routes/teams/sender';
import type { NavItem } from '@/types';

type NavGroup = {
    label: string;
    items: NavItem[];
};

export default function SettingsLayout({ children }: PropsWithChildren) {
    const { isCurrentOrParentUrl } = useCurrentUrl();
    const { currentTeam } = usePage().props;
    const [query, setQuery] = useState('');

    const filteredGroups = useMemo(() => {
        const canManageWorkspace = currentTeam?.role !== 'member';
        const navGroups: NavGroup[] = [
            {
                label: 'Account',
                items: [
                    { title: 'Profile', href: edit(), icon: UserIcon },
                    {
                        title: 'Password & security',
                        href: editSecurity(),
                        icon: LockPasswordIcon,
                    },
                    {
                        title: 'Appearance',
                        href: editAppearance(),
                        icon: SunMoonIcon,
                    },
                    ...(currentTeam?.role === 'owner'
                        ? [
                              {
                                  title: 'System Check',
                                  href: showSystemCheck(),
                                  icon: ShieldCheckIcon,
                              },
                          ]
                        : []),
                ],
            },
            {
                label: 'Workspace',
                items: [
                    ...(canManageWorkspace
                        ? [
                              {
                                  title: 'Workspace',
                                  href: currentTeam
                                      ? editTeam(currentTeam.slug)
                                      : teams(),
                                  icon: NewOfficeIcon,
                              },
                          ]
                        : []),
                    ...(currentTeam
                        ? [
                              {
                                  title: 'Members',
                                  href: teamMembers(currentTeam.slug),
                                  icon: UserAdd01Icon,
                              },
                              ...(currentTeam.role === 'owner'
                                  ? [
                                        {
                                            title: 'Roles',
                                            href: teamRoles(currentTeam.slug),
                                            icon: DashboardSquareSettingIcon,
                                        },
                                    ]
                                  : []),
                              {
                                  title: 'Tags',
                                  href: tagsIndex(currentTeam.slug),
                                  icon: TagsIcon,
                              },
                              ...(canManageWorkspace
                                  ? [
                                        {
                                            title: 'Email Editor',
                                            href: teamEmailSettings(
                                                currentTeam.slug,
                                            ),
                                            icon: MailEdit02Icon,
                                        },
                                        {
                                            title: 'Sender',
                                            href: teamSenderSettings(
                                                currentTeam.slug,
                                            ),
                                            icon: MailAtSign02Icon,
                                        },
                                        {
                                            title: 'Email delivery',
                                            href: teamEmailProviderSettings(
                                                currentTeam.slug,
                                            ),
                                            icon: MailSend02Icon,
                                        },
                                        {
                                            title: 'API Key',
                                            href: teamApiSettings(
                                                currentTeam.slug,
                                            ),
                                            icon: Key01Icon,
                                        },
                                    ]
                                  : []),
                          ]
                        : []),
                ],
            },
        ];

        const term = query.trim().toLowerCase();

        if (!term) {
            return navGroups;
        }

        return navGroups
            .map((group) => ({
                ...group,
                items: group.label.toLowerCase().includes(term)
                    ? group.items
                    : group.items.filter((item) =>
                          item.title.toLowerCase().includes(term),
                      ),
            }))
            .filter((group) => group.items.length > 0);
    }, [query, currentTeam]);

    // Tags live under the workspace URL, so the longest matching item wins to keep
    // a single entry highlighted instead of both.
    const activeHref = filteredGroups
        .flatMap((group) => group.items)
        .map((item) => toUrl(item.href))
        .filter((href) => isCurrentOrParentUrl(href))
        .sort((a, b) => b.length - a.length)
        .at(0);

    const dashboardUrl = currentTeam ? dashboard(currentTeam.slug) : '/';

    return (
        <SidebarProvider defaultOpen>
            <Sidebar collapsible="icon">
                <SidebarHeader>
                    <Button
                        variant="ghost"
                        size="sm"
                        className="justify-start gap-1.5 group-data-[collapsible=icon]:justify-center group-data-[collapsible=icon]:px-0"
                        nativeButton={false}
                        render={<Link href={dashboardUrl} />}
                    >
                        <HugeiconsIcon
                            icon={ArrowLeft01Icon}
                            data-icon="inline-start"
                        />
                        <span className="group-data-[collapsible=icon]:hidden">
                            Back to app
                        </span>
                    </Button>

                    <div className="relative group-data-[collapsible=icon]:hidden">
                        <HugeiconsIcon
                            icon={Search01Icon}
                            className="pointer-events-none absolute top-1/2 left-2 size-4 -translate-y-1/2 text-muted-foreground"
                        />
                        <SidebarInput
                            value={query}
                            onChange={(e) => setQuery(e.target.value)}
                            placeholder="Search settings..."
                            className="pl-7"
                        />
                    </div>
                </SidebarHeader>

                <SidebarContent>
                    {filteredGroups.map((group) => (
                        <SidebarGroup key={group.label}>
                            <SidebarGroupLabel>{group.label}</SidebarGroupLabel>
                            <SidebarGroupContent>
                                <SidebarMenu>
                                    {group.items.map((item) => (
                                        <SidebarMenuItem key={toUrl(item.href)}>
                                            <SidebarMenuButton
                                                isActive={
                                                    toUrl(item.href) ===
                                                    activeHref
                                                }
                                                tooltip={item.title}
                                                className="text-sidebar-foreground/70"
                                                render={
                                                    <Link
                                                        href={item.href}
                                                        prefetch
                                                    />
                                                }
                                            >
                                                {item.icon ? (
                                                    <HugeiconsIcon
                                                        icon={item.icon}
                                                    />
                                                ) : null}
                                                <span>{item.title}</span>
                                            </SidebarMenuButton>
                                        </SidebarMenuItem>
                                    ))}
                                </SidebarMenu>
                            </SidebarGroupContent>
                        </SidebarGroup>
                    ))}

                    {filteredGroups.length === 0 ? (
                        <p className="px-4 text-sm text-muted-foreground group-data-[collapsible=icon]:hidden">
                            No settings match “{query}”.
                        </p>
                    ) : null}
                </SidebarContent>

                <SidebarFooter>
                    <NavUser />
                </SidebarFooter>
                <SidebarRail />
            </Sidebar>

            <SidebarInset>
                <div className="mx-auto flex min-h-0 w-full max-w-4xl flex-1 flex-col">
                    <div className="flex h-16 shrink-0 items-center px-10 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:hidden">
                        <SidebarTrigger className="-ml-1" />
                    </div>
                    <div className="flex min-h-0 flex-1 flex-col p-10">
                        {children}
                    </div>
                </div>
            </SidebarInset>
        </SidebarProvider>
    );
}
