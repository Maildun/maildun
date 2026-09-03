<?php

test('workspace switching lives in the user dropdown, not a workspace list page', function () {
    $root = dirname(__DIR__, 2);
    $userMenu = file_get_contents($root.'/resources/js/components/user-menu-content.tsx');
    $teamSwitcher = file_get_contents($root.'/resources/js/components/team-switcher.tsx');
    $appHeader = file_get_contents($root.'/resources/js/components/app-header.tsx');
    $settingsLayout = file_get_contents($root.'/resources/js/layouts/settings/layout.tsx');
    $teamEdit = file_get_contents($root.'/resources/js/pages/teams/edit.tsx');

    expect($userMenu)->toBeString()
        ->toContain('<TeamSwitcher />');
    expect($teamSwitcher)->toBeString()
        ->toContain('DropdownMenuSub')
        ->toContain('<DropdownMenuLabel>Workspaces</DropdownMenuLabel>')
        ->toContain('Switch workspace')
        ->toContain('data-test="team-switcher-trigger"')
        ->toContain('data-test="team-switcher-item"')
        ->toContain('data-test="team-switcher-new-team"')
        ->toContain('<TeamLogo team={currentTeam} />')
        ->toContain('<TeamLogo team={team} />')
        ->toContain('src={team.logo}')
        ->toContain('render={<Link href={create()} prefetch />}')
        ->toContain('router.visit(switchMethod(team.slug))')
        ->not->toContain('currentUrl.replace')
        ->not->toContain('inHeader');
    expect($appHeader)->toBeString()
        ->toContain('<UserMenuContent')
        ->not->toContain('<TeamSwitcher');
    expect($settingsLayout)->toBeString()
        ->toContain('editTeam(currentTeam.slug)')
        ->toContain('teamMembers(currentTeam.slug)');
    expect($teamEdit)->toBeString()
        ->toContain('data-test="leave-team-button"')
        ->toContain('data-test="manage-members-button"')
        ->toContain('placeholder="Acme"')
        ->not->toContain('data-test="team-row"')
        ->not->toContain('data-test="member-row"');
    expect(file_get_contents($root.'/resources/js/pages/teams/members.tsx'))
        ->toBeString()
        ->toContain('data-test="member-row"')
        ->toContain('data-test="invite-member-button"');
    expect(file_exists($root.'/resources/js/pages/teams/index.tsx'))->toBeFalse();
});

test('creating a workspace opens a dedicated page instead of a user-menu dialog', function () {
    $root = dirname(__DIR__, 2);
    $teamSwitcher = file_get_contents($root.'/resources/js/components/team-switcher.tsx');
    $navUser = file_get_contents($root.'/resources/js/components/nav-user.tsx');
    $appHeader = file_get_contents($root.'/resources/js/components/app-header.tsx');
    $app = file_get_contents($root.'/resources/js/app.tsx');
    $createPage = file_get_contents($root.'/resources/js/pages/teams/create.tsx');

    expect($teamSwitcher)->toBeString()
        ->toContain('href={create()}')
        ->not->toContain('useCreateTeam')
        ->not->toContain('onClick={openCreateTeam}');
    expect($navUser)->toBeString()
        ->not->toContain('<CreateTeamModal>');
    expect($appHeader)->toBeString()
        ->not->toContain('<CreateTeamModal>');
    expect($app)->toBeString()
        ->toContain("case name === 'teams/create':")
        ->toContain('return null;');
    expect($createPage)->toBeString()
        ->toContain('Create workspace')
        ->toContain('data-test="create-workspace-name"')
        ->toContain('data-test="create-workspace-submit"')
        ->toContain('encType="multipart/form-data"')
        ->toContain('bg-muted/50')
        ->toContain('<aside className="grainy')
        ->toContain('max-w-6xl')
        ->toContain('lg:min-h-[42rem]')
        ->toContain('rounded-l-3xl')
        ->not->toContain('rounded-[2rem]')
        ->not->toContain('border-border/70');
    expect(file_exists($root.'/resources/js/components/create-team-modal.tsx'))->toBeFalse();
});

test('user dropdown constrains and truncates profile and workspace labels', function () {
    $root = dirname(__DIR__, 2);
    $navUser = file_get_contents($root.'/resources/js/components/nav-user.tsx');
    $userInfo = file_get_contents($root.'/resources/js/components/user-info.tsx');
    $teamSwitcher = file_get_contents($root.'/resources/js/components/team-switcher.tsx');

    expect($navUser)->toContain('w-56 max-w-[calc(100vw-2rem)]');
    expect($userInfo)->toContain('grid min-w-0 flex-1')
        ->toContain('truncate font-medium')
        ->toContain('truncate text-xs text-muted-foreground');
    expect($teamSwitcher)->toContain('className="w-full min-w-0"')
        ->toContain('w-56 max-w-[calc(100vw-2rem)]')
        ->toContain('min-w-0 flex-1 truncate')
        ->toContain('className="ml-auto shrink-0"');
});
