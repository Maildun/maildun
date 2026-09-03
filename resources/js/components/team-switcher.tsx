import {
    Add01Icon,
    Tick02Icon,
    UserGroupIcon,
} from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { Link, router, usePage } from '@inertiajs/react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuSub,
    DropdownMenuSubContent,
    DropdownMenuSubTrigger,
} from '@/components/ui/dropdown-menu';
import { useInitials } from '@/hooks/use-initials';
import { create, switchMethod } from '@/routes/teams';
import type { Team } from '@/types';

export function TeamSwitcher() {
    const page = usePage();
    const currentTeam = page.props.currentTeam;
    const teams = page.props.teams ?? [];

    const switchTeam = (team: Team) => {
        router.visit(switchMethod(team.slug));
    };

    return (
        <DropdownMenuSub>
            <DropdownMenuSubTrigger
                className="w-full min-w-0"
                data-test="team-switcher-trigger"
            >
                <TeamLogo team={currentTeam} />
                <span className="min-w-0 flex-1 truncate">
                    {currentTeam?.name ?? 'Switch workspace'}
                </span>
            </DropdownMenuSubTrigger>
            <DropdownMenuSubContent className="w-56 max-w-[calc(100vw-2rem)]">
                <DropdownMenuGroup>
                    <DropdownMenuLabel>Workspaces</DropdownMenuLabel>
                    {teams.map((team) => (
                        <DropdownMenuItem
                            key={team.id}
                            data-test="team-switcher-item"
                            className="min-w-0 cursor-pointer gap-2"
                            onClick={() => switchTeam(team)}
                        >
                            <TeamLogo team={team} />
                            <span className="min-w-0 flex-1 truncate">
                                {team.name}
                            </span>
                            {currentTeam?.id === team.id ? (
                                <HugeiconsIcon
                                    icon={Tick02Icon}
                                    className="ml-auto shrink-0"
                                />
                            ) : null}
                        </DropdownMenuItem>
                    ))}
                </DropdownMenuGroup>
                <DropdownMenuSeparator />
                <DropdownMenuGroup>
                    <DropdownMenuItem
                        data-test="team-switcher-new-team"
                        className="cursor-pointer gap-2"
                        render={<Link href={create()} prefetch />}
                    >
                        <HugeiconsIcon icon={Add01Icon} />
                        <span className="text-muted-foreground">
                            New workspace
                        </span>
                    </DropdownMenuItem>
                </DropdownMenuGroup>
            </DropdownMenuSubContent>
        </DropdownMenuSub>
    );
}

function TeamLogo({ team }: { team?: Team | null }) {
    const getInitials = useInitials();

    return (
        <Avatar size="sm" className="rounded-md after:rounded-md">
            {team ? (
                <AvatarImage
                    src={team.logo}
                    alt={team.name}
                    className="rounded-md"
                />
            ) : null}
            <AvatarFallback className="rounded-md">
                {team ? (
                    getInitials(team.name)
                ) : (
                    <HugeiconsIcon icon={UserGroupIcon} />
                )}
            </AvatarFallback>
        </Avatar>
    );
}
