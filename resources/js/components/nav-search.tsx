import { SearchIcon } from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import {
    CommandDialog,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import { Kbd, KbdGroup } from '@/components/ui/kbd';
import {
    SidebarGroup,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { toUrl } from '@/lib/utils';
import type { NavItem } from '@/types';

export function NavSearch({ items }: { items: NavItem[] }) {
    const [open, setOpen] = useState(false);

    useEffect(() => {
        function onKeyDown(event: KeyboardEvent) {
            if (
                event.key.toLowerCase() === 'k' &&
                (event.metaKey || event.ctrlKey)
            ) {
                event.preventDefault();
                setOpen((value) => !value);
            }
        }

        document.addEventListener('keydown', onKeyDown);

        return () => document.removeEventListener('keydown', onKeyDown);
    }, []);

    function navigate(href: NavItem['href']) {
        setOpen(false);
        router.visit(toUrl(href));
    }

    return (
        <>
            <SidebarGroup className="px-2 py-0">
                <SidebarMenu>
                    <SidebarMenuItem className="after:hidden">
                        <SidebarMenuButton
                            variant="secondary"
                            tooltip={{ children: 'Search' }}
                            onClick={() => setOpen(true)}
                        >
                            <HugeiconsIcon icon={SearchIcon} />
                            <span>Search</span>
                            <KbdGroup className="ml-auto group-data-[collapsible=icon]:hidden">
                                <Kbd>⌘</Kbd>
                                <Kbd>K</Kbd>
                            </KbdGroup>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarGroup>

            <CommandDialog
                open={open}
                onOpenChange={setOpen}
                title="Quick navigation"
                description="Jump to any section"
            >
                <CommandInput placeholder="Search pages..." />
                <CommandList>
                    <CommandEmpty>No results found.</CommandEmpty>
                    <CommandGroup heading="Pages">
                        {items.map((item) => (
                            <CommandItem
                                key={item.title}
                                value={item.title}
                                onSelect={() => navigate(item.href)}
                            >
                                {item.icon && (
                                    <HugeiconsIcon icon={item.icon} />
                                )}
                                <span>{item.title}</span>
                            </CommandItem>
                        ))}
                    </CommandGroup>
                </CommandList>
            </CommandDialog>
        </>
    );
}
