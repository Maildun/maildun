import {
    ArrowUpRight01Icon,
    BookOpen02Icon,
    BubbleChatQuestionIcon,
    ComputerIcon,
    GithubIcon,
    Logout02Icon,
    Moon02Icon,
    Settings01Icon,
    Sun01Icon,
    SunMoon as SunMoonIcon,
} from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import type { IconSvgElement } from '@hugeicons/react';
import { Link, router } from '@inertiajs/react';
import { TeamSwitcher } from '@/components/team-switcher';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuSeparator,
    DropdownMenuSub,
    DropdownMenuSubContent,
    DropdownMenuSubTrigger,
} from '@/components/ui/dropdown-menu';
import { UserInfo } from '@/components/user-info';
import type { Appearance } from '@/hooks/use-appearance';
import { useAppearance } from '@/hooks/use-appearance';
import { useMobileNavigation } from '@/hooks/use-mobile-navigation';
import { logout } from '@/routes';
import { edit } from '@/routes/profile';
import type { User } from '@/types';

const DOCUMENTATION_URL = 'https://github.com/abduns/maildun/tree/main/docs';
const REPOSITORY_URL = 'https://github.com/abduns/maildun';
const ISSUES_URL = 'https://github.com/abduns/maildun/issues';

const THEME_OPTIONS: {
    value: Appearance;
    label: string;
    icon: IconSvgElement;
}[] = [
    { value: 'light', label: 'Light', icon: Sun01Icon },
    { value: 'dark', label: 'Dark', icon: Moon02Icon },
    { value: 'system', label: 'System', icon: ComputerIcon },
];

type Props = {
    user: User;
};

export function UserMenuContent({ user }: Props) {
    const cleanup = useMobileNavigation();

    const handleLogout = () => {
        cleanup();
        router.flushAll();
    };

    return (
        <>
            <div className="flex items-center gap-2 px-1 py-1.5 text-left text-sm text-muted-foreground">
                <UserInfo user={user} showEmail={true} />
            </div>
            <DropdownMenuSeparator />
            <DropdownMenuGroup>
                <TeamSwitcher />
            </DropdownMenuGroup>
            <DropdownMenuSeparator />
            <DropdownMenuGroup>
                <ThemeMenu />
                <DropdownMenuItem
                    render={
                        <Link
                            className="block w-full cursor-pointer"
                            href={edit()}
                            prefetch
                            onClick={cleanup}
                        />
                    }
                >
                    <HugeiconsIcon icon={Settings01Icon} />
                    Settings
                </DropdownMenuItem>
            </DropdownMenuGroup>
            <DropdownMenuSeparator />
            <DropdownMenuGroup>
                <DropdownMenuItem
                    className="group justify-between"
                    nativeButton={false}
                    data-test="documentation-link"
                    render={
                        <a
                            href={DOCUMENTATION_URL}
                            target="_blank"
                            rel="noreferrer"
                            className="block w-full cursor-pointer"
                        />
                    }
                >
                    <span className="flex items-center gap-2">
                        <HugeiconsIcon icon={BookOpen02Icon} />
                        Documentation
                    </span>
                    <HugeiconsIcon
                        icon={ArrowUpRight01Icon}
                        className="motion-safe:transition-transform motion-safe:duration-200 motion-safe:group-hover:translate-x-0.5 motion-safe:group-hover:-translate-y-0.5"
                    />
                </DropdownMenuItem>
                <DropdownMenuItem
                    className="group justify-between"
                    nativeButton={false}
                    data-test="repository-link"
                    render={
                        <a
                            href={REPOSITORY_URL}
                            target="_blank"
                            rel="noreferrer"
                            className="block w-full cursor-pointer"
                        />
                    }
                >
                    <span className="flex items-center gap-2">
                        <HugeiconsIcon icon={GithubIcon} />
                        GitHub
                    </span>
                    <HugeiconsIcon
                        icon={ArrowUpRight01Icon}
                        className="motion-safe:transition-transform motion-safe:duration-200 motion-safe:group-hover:translate-x-0.5 motion-safe:group-hover:-translate-y-0.5"
                    />
                </DropdownMenuItem>
                <DropdownMenuItem
                    className="group justify-between"
                    nativeButton={false}
                    data-test="report-issue-link"
                    render={
                        <a
                            href={ISSUES_URL}
                            target="_blank"
                            rel="noreferrer"
                            className="block w-full cursor-pointer"
                        />
                    }
                >
                    <span className="flex items-center gap-2">
                        <HugeiconsIcon icon={BubbleChatQuestionIcon} />
                        Report an issue
                    </span>
                    <HugeiconsIcon
                        icon={ArrowUpRight01Icon}
                        className="motion-safe:transition-transform motion-safe:duration-200 motion-safe:group-hover:translate-x-0.5 motion-safe:group-hover:-translate-y-0.5"
                    />
                </DropdownMenuItem>
            </DropdownMenuGroup>
            <DropdownMenuSeparator />
            <DropdownMenuItem
                nativeButton
                render={
                    <Link
                        className="block w-full cursor-pointer"
                        href={logout()}
                        as="button"
                        onClick={handleLogout}
                        data-test="logout-button"
                    />
                }
            >
                <HugeiconsIcon icon={Logout02Icon} />
                Log out
            </DropdownMenuItem>
        </>
    );
}

function ThemeMenu() {
    const { appearance, updateAppearance } = useAppearance();

    return (
        <DropdownMenuSub>
            <DropdownMenuSubTrigger data-test="theme-menu-trigger">
                <HugeiconsIcon icon={SunMoonIcon} />
                Theme
            </DropdownMenuSubTrigger>
            <DropdownMenuSubContent>
                <DropdownMenuRadioGroup
                    value={appearance}
                    onValueChange={(value) =>
                        updateAppearance(value as Appearance)
                    }
                >
                    {THEME_OPTIONS.map(({ value, label, icon }) => (
                        <DropdownMenuRadioItem
                            key={value}
                            value={value}
                            data-test={`theme-${value}-item`}
                        >
                            <HugeiconsIcon icon={icon} />
                            {label}
                        </DropdownMenuRadioItem>
                    ))}
                </DropdownMenuRadioGroup>
            </DropdownMenuSubContent>
        </DropdownMenuSub>
    );
}
