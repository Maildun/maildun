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
import type { PropsWithChildren } from 'react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
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
};

export default function AudienceSettingsLayout({
    audience,
    children,
}: PropsWithChildren<{
    audience: Pick<Audience, 'uuid' | 'name' | 'avatar'>;
}>) {
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
        },
        {
            key: 'sender',
            title: 'Sender',
            href: toUrl(sender(routeArgs)),
            icon: MailSend01Icon,
        },
        {
            key: 'notifications',
            title: 'Email notification',
            href: toUrl(notifications(routeArgs)),
            icon: Notification03Icon,
        },
        {
            key: 'double-opt-in',
            title: 'Double opt-in',
            href: toUrl(doubleOptIn(routeArgs)),
            icon: MailOpen01Icon,
        },
        {
            key: 'attributes',
            title: 'Attributes',
            href: toUrl(attributes(routeArgs)),
            icon: LeftToRightListBulletIcon,
        },
        {
            key: 'landing-pages',
            title: 'Landing pages',
            href: toUrl(landingPages(routeArgs)),
            icon: LinkSquare02Icon,
        },
        {
            key: 'hygiene',
            title: 'List hygiene',
            href: toUrl(hygiene(routeArgs)),
            icon: CleanIcon,
        },
        {
            key: 'danger',
            title: 'Danger zone',
            href: toUrl(danger(routeArgs)),
            icon: DangerIcon,
        },
    ];

    return (
        <div className="flex flex-col gap-8 lg:flex-row lg:items-start lg:gap-12">
            <aside className="flex flex-col gap-6 lg:sticky lg:top-10 lg:w-52 lg:shrink-0">
                <div className="flex items-center gap-3">
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
                        <p className="truncate text-sm font-medium">
                            {audience.name}
                        </p>
                        <Link
                            href={show(routeArgs)}
                            prefetch
                            className="text-xs text-muted-foreground underline-offset-2 hover:underline"
                        >
                            View audience
                        </Link>
                    </div>
                </div>

                <nav className="flex flex-wrap gap-1 lg:flex-col">
                    {items.map((item) => (
                        <Button
                            key={item.key}
                            variant={
                                isCurrentUrl(item.href) ? 'secondary' : 'ghost'
                            }
                            size="sm"
                            className="justify-start lg:w-full"
                            nativeButton={false}
                            data-test={`audience-settings-nav-${item.key}`}
                            render={<Link href={item.href} prefetch />}
                        >
                            <HugeiconsIcon
                                icon={item.icon}
                                data-icon="inline-start"
                            />
                            {item.title}
                        </Button>
                    ))}
                </nav>
            </aside>

            <div className="min-w-0 flex-1 lg:max-w-3xl">{children}</div>
        </div>
    );
}
