import { UnfoldMoreIcon } from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { usePage } from '@inertiajs/react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { UserInfo } from '@/components/user-info';
import { UserMenuContent } from '@/components/user-menu-content';
import { useIsMobile } from '@/hooks/use-mobile';

export function NavUser() {
    const { auth, currentTeam } = usePage().props;
    const isMobile = useIsMobile();

    return (
        <SidebarMenu>
            <SidebarMenuItem className="after:hidden">
                <DropdownMenu>
                    <DropdownMenuTrigger
                        render={
                            <SidebarMenuButton
                                size="lg"
                                className="group text-sidebar-accent-foreground data-popup-open:bg-sidebar-accent"
                                data-test="sidebar-menu-button"
                            />
                        }
                    >
                        <UserInfo user={auth.user} team={currentTeam} />
                        <HugeiconsIcon
                            icon={UnfoldMoreIcon}
                            className="ml-auto"
                        />
                    </DropdownMenuTrigger>
                    <DropdownMenuContent
                        className="w-56 max-w-[calc(100vw-2rem)] rounded-lg"
                        align="end"
                        side={isMobile ? 'bottom' : 'right'}
                        sideOffset={4}
                    >
                        <UserMenuContent user={auth.user} />
                    </DropdownMenuContent>
                </DropdownMenu>
            </SidebarMenuItem>
        </SidebarMenu>
    );
}
