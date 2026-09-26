<?php

test('email index follows the audience plain table presentation', function () {
    $index = file_get_contents(dirname(__DIR__, 2).'/resources/js/pages/emails/index.tsx');

    expect($index)->toBeString()
        ->toContain('<div className="flex flex-1 flex-col gap-6">')
        ->toContain('<Table>')
        ->toContain('<Head title="Campaigns" />')
        ->toContain("'No campaigns yet'")
        ->toContain('Compose campaign')
        ->toMatch('/<TableHead>Status<\/TableHead>\s*<TableHead>Campaign<\/TableHead>/')
        ->toContain('<TableHead>Campaign</TableHead>')
        ->toContain('<TableHead>Recipients</TableHead>')
        ->toContain('data-test="email-status"')
        ->toContain('data-test="email-recipient-avatars"')
        ->toContain("notation: 'compact'")
        ->toContain('maximumFractionDigits: 1')
        ->toContain('formatRecipientOverflow(')
        ->toContain('<AvatarGroupCount className="w-auto! min-w-6 px-1 text-[10px] tabular-nums">')
        ->toContain('data-test="email-row"')
        ->toContain('data-test="campaign-name-link"')
        ->toContain('className="font-medium underline-offset-4 hover:underline"')
        ->toMatch('/isDraft\\(email.status\\)\\s*\\?\\s*edit\\(\\[/')
        ->toContain('<div className="flex min-w-0 flex-col">')
        ->toContain('<TableCell className="text-right">')
        ->toContain('PieChartIcon')
        ->toMatch('/icon=\\{\\s*isDraft\\(\\s*email.status,?\\s*\\)\\s*\\?\\s*Edit03Icon\\s*:\\s*PieChartIcon\\s*\\}/s')
        ->toContain('<Empty>')
        ->not->toContain("from '@/components/ui/card'")
        ->not->toContain('<Card>');
});

test('campaign terminology is used throughout the drafting interface', function () {
    $sidebar = file_get_contents(dirname(__DIR__, 2).'/resources/js/components/app-sidebar.tsx');
    $campaignNavigation = file_get_contents(dirname(__DIR__, 2).'/resources/js/components/nav-campaigns.tsx');
    $picker = file_get_contents(dirname(__DIR__, 2).'/resources/js/components/email-template-picker.tsx');
    $editor = file_get_contents(dirname(__DIR__, 2).'/resources/js/pages/emails/edit.tsx');
    $deleteModal = file_get_contents(dirname(__DIR__, 2).'/resources/js/components/delete-email-modal.tsx');

    expect($sidebar)->toBeString()
        ->toContain("title: 'Campaigns'")
        ->toContain("title: 'Transactional'")
        ->toContain("title: 'Templates'")
        ->and($campaignNavigation)->toBeString()
        ->toContain("import { index, show as showEmail } from '@/routes/emails';")
        ->toMatch('/<SidebarGroupLabel\\s+className="hover:text-sidebar-foreground"\\s+render=\\{\\s*<Link href=\\{index\\(currentTeam\\.slug\\)\\} prefetch \\/>\\s*\\}/s')
        ->and($picker)->toBeString()
        ->toContain("title = 'Compose campaign'")
        ->toContain("emptyLabel = 'Empty campaign'")
        ->and($editor)->toBeString()
        ->toContain('Back to campaigns')
        ->toContain('Preview and Send')
        ->toContain('Rename campaign')
        ->toContain('Delete campaign')
        ->and($deleteModal)->toBeString()
        ->toContain('<DialogTitle>Delete campaign</DialogTitle>')
        ->toContain('Delete campaign');
});
