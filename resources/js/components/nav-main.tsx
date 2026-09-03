import { ArrowRight01Icon } from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { Link } from '@inertiajs/react';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import type { NavItem } from '@/types';

export function NavMain({
    items = [],
    label = 'Platform',
}: {
    items: NavItem[];
    label?: string;
}) {
    const { isCurrentOrParentUrl, isCurrentUrl } = useCurrentUrl();

    return (
        <SidebarGroup className="px-2 py-0">
            <SidebarGroupLabel>{label}</SidebarGroupLabel>
            <SidebarMenu>
                {items.map((item) => {
                    const hasChildren = Boolean(item.items?.length);
                    const isActive = hasChildren
                        ? isCurrentOrParentUrl(item.href)
                        : isCurrentUrl(item.href);

                    if (!hasChildren) {
                        return (
                            <SidebarMenuItem key={item.title}>
                                <SidebarMenuButton
                                    isActive={isActive}
                                    tooltip={{ children: item.title }}
                                    className="text-sidebar-foreground/70"
                                    render={<Link href={item.href} prefetch />}
                                >
                                    {item.icon && (
                                        <HugeiconsIcon icon={item.icon} />
                                    )}
                                    <span>{item.title}</span>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        );
                    }

                    return (
                        <Collapsible
                            key={item.title}
                            defaultOpen={isActive}
                            className="group/collapsible"
                            render={<SidebarMenuItem />}
                        >
                            <CollapsibleTrigger
                                render={
                                    <SidebarMenuButton
                                        isActive={isActive}
                                        tooltip={{ children: item.title }}
                                        className="text-sidebar-foreground/70"
                                    />
                                }
                            >
                                {item.icon && (
                                    <HugeiconsIcon icon={item.icon} />
                                )}
                                <span>{item.title}</span>
                                <HugeiconsIcon
                                    icon={ArrowRight01Icon}
                                    className="ml-auto transition-transform duration-200 group-has-data-[panel-open]/collapsible:rotate-90"
                                />
                            </CollapsibleTrigger>
                            <CollapsibleContent>
                                <SidebarMenuSub>
                                    {item.items?.map((subItem) => (
                                        <SidebarMenuSubItem key={subItem.title}>
                                            <SidebarMenuSubButton
                                                isActive={isCurrentUrl(
                                                    subItem.href,
                                                )}
                                                className="text-sidebar-foreground/70"
                                                render={
                                                    <Link
                                                        href={subItem.href}
                                                        prefetch
                                                    />
                                                }
                                            >
                                                <span>{subItem.title}</span>
                                            </SidebarMenuSubButton>
                                        </SidebarMenuSubItem>
                                    ))}
                                </SidebarMenuSub>
                            </CollapsibleContent>
                        </Collapsible>
                    );
                })}
            </SidebarMenu>
        </SidebarGroup>
    );
}
