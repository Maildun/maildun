<?php

test('sidebar components use Hugeicons instead of Lucide', function () {
    $sidebarComponents = [
        'resources/js/components/app-sidebar.tsx',
        'resources/js/components/nav-main.tsx',
        'resources/js/components/nav-footer.tsx',
        'resources/js/components/nav-user.tsx',
        'resources/js/components/team-switcher.tsx',
        'resources/js/components/user-menu-content.tsx',
        'resources/js/components/ui/sidebar.tsx',
    ];

    foreach ($sidebarComponents as $sidebarComponent) {
        $source = file_get_contents(dirname(__DIR__, 2).'/'.$sidebarComponent);

        expect($source)->toBeString()->not->toContain('lucide-react');
    }
});

test('sidebar trigger reflects whether the sidebar is expanded or collapsed', function () {
    $sidebar = file_get_contents(dirname(__DIR__, 2).'/resources/js/components/ui/sidebar.tsx');

    expect($sidebar)->toBeString()
        ->toContain('LayoutAlignLeftIcon, LayoutAlignRightIcon')
        ->toContain('const { state, toggleSidebar } = useSidebar()')
        ->toContain('state === "expanded" ? LayoutAlignLeftIcon : LayoutAlignRightIcon');
});

test('the application uses Hugeicons everywhere instead of Lucide', function () {
    $root = dirname(__DIR__, 2);

    $sources = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root.'/resources/js', RecursiveDirectoryIterator::SKIP_DOTS)
    );

    $offenders = [];

    foreach ($sources as $source) {
        if (! $source->isFile() || ! in_array($source->getExtension(), ['ts', 'tsx'], true)) {
            continue;
        }

        if (str_contains(file_get_contents($source->getPathname()), 'lucide-react')) {
            $offenders[] = str_replace($root.'/', '', $source->getPathname());
        }
    }

    expect($offenders)->toBe([]);
    expect(json_decode(file_get_contents($root.'/package.json'), true))
        ->not->toHaveKey('dependencies.lucide-react')
        ->not->toHaveKey('devDependencies.lucide-react');
});

test('app logo mark is a customizable inline SVG', function () {
    $root = dirname(__DIR__, 2);
    $logoComponent = file_get_contents($root.'/resources/js/components/app-logo-icon.tsx');

    expect($logoComponent)->toBeString()
        ->toContain('SVGProps<SVGSVGElement>')
        ->toContain('viewBox="0 0 63.72 70.16"')
        ->toContain('fill="currentColor"')
        ->toContain('color?: string;')
        ->toContain("animate?: 'none' | 'pulse' | 'spin';")
        ->toContain("'motion-safe:animate-pulse'")
        ->toContain("'motion-safe:animate-spin'")
        ->toContain("type LogoMode = 'theme' | 'light' | 'dark';")
        ->toContain('mode?: LogoMode;')
        ->toContain('title?: string;')
        ->toContain('aria-hidden={props[\'aria-hidden\'] ?? isDecorative}')
        ->toContain('<svg')
        ->toContain('M58.72,36.08');
});

test('app logo wordmark composes the customizable mark and product label', function () {
    $root = dirname(__DIR__, 2);
    $logoComponent = file_get_contents($root.'/resources/js/components/app-logo-icon.tsx');

    expect($logoComponent)->toBeString()
        ->toContain('export function AppLogoWordmark')
        ->toContain("label = 'Maildun'")
        ->toContain('<AppLogoIcon animate={animate}')
        ->toContain('style={{ color: color ?? markColors[mode], ...style }}');
});

test('the application favicon uses the provided logo assets', function () {
    $root = dirname(__DIR__, 2);
    $appView = file_get_contents($root.'/resources/views/app.blade.php');

    expect($appView)->toBeString()
        ->toContain('href="/assets/img/logo.svg"')
        ->toContain('href="/assets/img/logo-white.svg"')
        ->not->toContain('href="/favicon.svg"');
});

test('sidebar keeps the sidebar-07 layout primitives', function () {
    $appSidebar = file_get_contents(dirname(__DIR__, 2).'/resources/js/components/app-sidebar.tsx');
    $navMain = file_get_contents(dirname(__DIR__, 2).'/resources/js/components/nav-main.tsx');

    expect($appSidebar)->toBeString()
        ->toContain('<AppLogo showName={false} />')
        ->toContain('<SidebarMenuButton')
        ->toContain('size="lg"')
        ->toContain('[&_svg]:size-5!')
        ->toContain('hover:bg-transparent!')
        ->toContain('active:bg-transparent!')
        ->toContain('SidebarTrigger')
        ->toContain('text-sidebar-foreground/70 hover:bg-sidebar-accent hover:text-sidebar-accent-foreground')
        ->toContain('group/logo')
        ->toContain('group-hover/logo:opacity-100')
        ->toContain('<SidebarRail />')
        ->toContain('<NavUser />')
        ->not->toContain('<TeamSwitcher />')
        ->not->toContain('Repository')
        ->not->toContain('Documentation')
        ->not->toContain('NavFooter');
    expect(file_get_contents(dirname(__DIR__, 2).'/resources/js/components/app-header.tsx'))
        ->toBeString()
        ->not->toContain('Repository')
        ->not->toContain('Documentation');
    $appLogo = file_get_contents(
        dirname(__DIR__, 2).'/resources/js/components/app-logo.tsx',
    );

    expect($appLogo)->toBeString()
        ->toContain('group-data-[collapsible=icon]:hidden')
        ->toContain('<AppLogoIcon mode="theme" className="size-6" />')
        ->toContain('showName?: boolean;')
        ->not->toContain('bg-sidebar-primary')
        ->not->toContain('text-sidebar-primary-foreground');
    expect($appSidebar)->toContain('<AppLogo showName={false} />');
    expect(file_get_contents(dirname(__DIR__, 2).'/resources/js/components/app-sidebar-header.tsx'))
        ->toBeString()
        ->toContain('md:hidden');
    expect($navMain)->toBeString()
        ->toContain('CollapsibleContent')
        ->toContain('SidebarMenuSubButton');
    expect(file_get_contents(dirname(__DIR__, 2).'/resources/js/components/user-menu-content.tsx'))
        ->toBeString()
        ->toContain('<TeamSwitcher />')
        ->toContain('<HugeiconsIcon icon={Logout02Icon} />')
        ->not->toContain('Logout01Icon');
    expect(file_get_contents(dirname(__DIR__, 2).'/resources/js/components/team-switcher.tsx'))
        ->toBeString()
        ->toContain('DropdownMenuSub')
        ->not->toContain('SidebarMenuButton');
});

test('the user dropdown provides theme and product resource links', function () {
    $root = dirname(__DIR__, 2);
    $userMenu = file_get_contents(
        $root.'/resources/js/components/user-menu-content.tsx',
    );

    expect($userMenu)->toBeString()
        ->toContain('const DOCUMENTATION_URL = \'https://github.com/abduns/maildun/tree/main/docs\';')
        ->toContain('const REPOSITORY_URL = \'https://github.com/abduns/maildun\';')
        ->toContain('const ISSUES_URL = \'https://github.com/abduns/maildun/issues\';')
        ->toContain('data-test="theme-menu-trigger"')
        ->toContain('data-test={`theme-${value}-item`}')
        ->toContain('ArrowUpRight01Icon')
        ->toContain('motion-safe:group-hover:-translate-y-0.5')
        ->toContain('motion-safe:group-hover:translate-x-0.5')
        ->toContain('icon={GithubIcon}')
        ->toContain('icon={BubbleChatQuestionIcon}')
        ->toContain('Documentation')
        ->toContain('GitHub')
        ->toContain('Report an issue')
        ->toContain('target="_blank"')
        ->toContain('rel="noreferrer"')
        ->toContain('value: \'light\'')
        ->toContain('value: \'dark\'')
        ->toContain('value: \'system\'');

    expect(substr_count($userMenu, 'icon={ArrowUpRight01Icon}'))->toBe(3);
    expect(substr_count($userMenu, 'className="group justify-between"'))->toBe(3);
});

test('sidebar search uses the secondary menu button variant', function () {
    $root = dirname(__DIR__, 2);
    $navSearch = file_get_contents($root.'/resources/js/components/nav-search.tsx');
    $sidebar = file_get_contents($root.'/resources/js/components/ui/sidebar.tsx');

    expect($navSearch)->toBeString()
        ->toContain('variant="secondary"')
        ->not->toContain('text-sidebar-foreground/70');

    expect($sidebar)->toBeString()
        ->toContain('secondary:')
        ->toContain('bg-secondary text-secondary-foreground');
});

test('sidebar includes the workspace list hygiene destination', function () {
    $appSidebar = file_get_contents(dirname(__DIR__, 2).'/resources/js/components/app-sidebar.tsx');

    expect($appSidebar)->toBeString()
        ->toContain("from '@/routes/list_hygiene'")
        ->toContain("title: 'List Hygiene'")
        ->toContain('icon: CleanIcon');
});

test('sidebar uses the requested workflow icon mapping', function () {
    $appSidebar = file_get_contents(dirname(__DIR__, 2).'/resources/js/components/app-sidebar.tsx');

    expect($appSidebar)->toBeString()
        ->toContain('icon: MenuCircleIcon')
        ->toContain('icon: MailAtSign02Icon')
        ->toContain('icon: MailSend02Icon')
        ->toContain('icon: FolderLibraryIcon')
        ->not->toContain('DashboardSquare01Icon')
        ->not->toContain('Mail01Icon')
        ->not->toContain('MailSend01Icon')
        ->not->toContain('Image01Icon');
});

test('sidebar separates audience and campaign management without recent campaigns', function () {
    $appSidebar = file_get_contents(dirname(__DIR__, 2).'/resources/js/components/app-sidebar.tsx');

    expect($appSidebar)->toBeString()
        ->toContain('<NavMain items={overviewNavItems} label="Overview" />')
        ->toContain('<NavMain items={audienceNavItems} label="Contacts" />')
        ->toContain('<NavMain items={campaignNavItems} label="Manage campaign" />')
        ->toContain('...overviewNavItems')
        ->toContain('...audienceNavItems')
        ->toContain('...campaignNavItems')
        ->not->toContain("from '@/components/nav-campaigns'")
        ->not->toContain('<NavCampaigns />');
});
