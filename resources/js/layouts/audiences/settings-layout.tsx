import {
    CleanIcon,
    DangerIcon,
    LeftToRightListBulletIcon,
    LinkSquare02Icon,
    MailOpen01Icon,
    MailSend01Icon,
    Notification03Icon,
    Settings02Icon,
} from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import type { IconSvgElement } from '@hugeicons/react';
import { Link, usePage } from '@inertiajs/react';
import { useState } from 'react';
import type { PropsWithChildren } from 'react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import {
    Sidebar,
    SidebarContent,
    SidebarGroup,
    SidebarGroupContent,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarTrigger,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { toUrl } from '@/lib/utils';
import { edit, show } from '@/routes/audiences';
import {
    attributes,
    danger,
    doubleOptIn,
    hygiene,
    landingPages,
    notifications,
    sender,
} from '@/routes/audiences/settings';
import type { Audience, AudienceSettingsPage } from '@/types/audiences';

type NavItem = {
    key: AudienceSettingsPage;
    title: string;
    href: string;
    icon: IconSvgElement;
    activeIcon: IconSvgElement;
};

type AudienceSettingsLayoutProps = PropsWithChildren<{
    audience: Pick<Audience, 'uuid' | 'name' | 'avatar'>;
}>;

export default function AudienceSettingsLayout({
    children,
}: AudienceSettingsLayoutProps) {
    return <div className="min-w-0">{children}</div>;
}

export function AudienceSettingsSidebar({
    audience,
}: Pick<AudienceSettingsLayoutProps, 'audience'>) {
    const [openMobile, setOpenMobile] = useState(false);
    const { currentTeam } = usePage().props;
    const { isCurrentUrl } = useCurrentUrl();

    if (!currentTeam) {
        return null;
    }

    const routeArgs: [string, string] = [currentTeam.slug, audience.uuid];
    const items: NavItem[] = [
        {
            key: 'general',
            title: 'General',
            href: toUrl(edit(routeArgs)),
            icon: Settings02Icon,
            activeIcon: Settings02Icon,
        },
        {
            key: 'sender',
            title: 'Sender',
            href: toUrl(sender(routeArgs)),
            icon: MailSend01Icon,
            activeIcon: MailSend01Icon,
        },
        {
            key: 'notifications',
            title: 'Email notification',
            href: toUrl(notifications(routeArgs)),
            icon: Notification03Icon,
            activeIcon: Notification03Icon,
        },
        {
            key: 'double-opt-in',
            title: 'Double opt-in',
            href: toUrl(doubleOptIn(routeArgs)),
            icon: MailOpen01Icon,
            activeIcon: MailOpen01Icon,
        },
        {
            key: 'attributes',
            title: 'Attributes',
            href: toUrl(attributes(routeArgs)),
            icon: LeftToRightListBulletIcon,
            activeIcon: LeftToRightListBulletIcon,
        },
        {
            key: 'landing-pages',
            title: 'Landing pages',
            href: toUrl(landingPages(routeArgs)),
            icon: LinkSquare02Icon,
            activeIcon: LinkSquare02Icon,
        },
        {
            key: 'hygiene',
            title: 'List hygiene',
            href: toUrl(hygiene(routeArgs)),
            icon: CleanIcon,
            activeIcon: CleanIcon,
        },
        {
            key: 'danger',
            title: 'Danger zone',
            href: toUrl(danger(routeArgs)),
            icon: DangerIcon,
            activeIcon: DangerIcon,
        },
    ];

    const audienceHeader = (
        <div className="flex min-w-0 items-center gap-3">
            <Avatar className="size-10 rounded-md after:rounded-md">
                <AvatarImage
                    src={audience.avatar}
                    alt=""
                    className="rounded-md"
                />
                <AvatarFallback className="rounded-md">
                    {audience.name.charAt(0).toUpperCase()}
                </AvatarFallback>
            </Avatar>
            <div className="min-w-0">
                <p className="truncate text-sm font-medium">{audience.name}</p>
                <Link
                    href={show(routeArgs)}
                    prefetch
                    onClick={() => setOpenMobile(false)}
                    className="text-xs text-muted-foreground underline-offset-2 hover:underline"
                >
                    View audience
                </Link>
            </div>
        </div>
    );

    const navigation = (
        <SidebarContent>
            <SidebarGroup>
                <SidebarGroupLabel>Audience settings</SidebarGroupLabel>
                <SidebarGroupContent>
                    <nav aria-label="Audience settings">
                        <SidebarMenu>
                            {items.map((item) => {
                                const isActive = isCurrentUrl(item.href);

                                return (
                                    <SidebarMenuItem key={item.key}>
                                        <SidebarMenuButton
                                            isActive={isActive}
                                            aria-current={
                                                isActive ? 'page' : undefined
                                            }
                                            className="text-sidebar-foreground/70"
                                            data-test={`audience-settings-nav-${item.key}`}
                                            render={
                                                <Link
                                                    href={item.href}
                                                    prefetch
                                                    onClick={() =>
                                                        setOpenMobile(false)
                                                    }
                                                />
                                            }
                                        >
                                            <HugeiconsIcon
                                                icon={
                                                    isActive
                                                        ? item.activeIcon
                                                        : item.icon
                                                }
                                            />
                                            <span>{item.title}</span>
                                        </SidebarMenuButton>
                                    </SidebarMenuItem>
                                );
                            })}
                        </SidebarMenu>
                    </nav>
                </SidebarGroupContent>
            </SidebarGroup>
        </SidebarContent>
    );

    return (
        <>
            <Sidebar
                collapsible="none"
                className="hidden w-60 shrink-0 border-r md:flex"
                aria-label="Audience settings sidebar"
            >
                <SidebarHeader className="gap-4 border-b p-4">
                    {audienceHeader}
                </SidebarHeader>
                {navigation}
            </Sidebar>

            <div className="flex h-12 shrink-0 items-center gap-2 border-b px-4 md:hidden">
                <SidebarTrigger />
                <Sheet open={openMobile} onOpenChange={setOpenMobile}>
                    <SheetTrigger render={<Button variant="ghost" size="sm" />}>
                        <HugeiconsIcon
                            icon={Settings02Icon}
                            data-icon="inline-start"
                        />
                        Audience settings
                    </SheetTrigger>
                    <SheetContent side="left" className="gap-0 p-0">
                        <SheetHeader>
                            <SheetTitle>Audience settings</SheetTitle>
                            <SheetDescription>{audience.name}</SheetDescription>
                        </SheetHeader>
                        <SidebarHeader className="border-b p-4">
                            {audienceHeader}
                        </SidebarHeader>
                        {navigation}
                    </SheetContent>
                </Sheet>
            </div>
        </>
    );
}
