<?php

test('transactional index follows the campaign plain table presentation', function () {
    $index = file_get_contents(dirname(__DIR__, 2).'/resources/js/pages/transactional/index.tsx');

    expect($index)->toBeString()
        ->toContain('<div className="flex flex-1 flex-col gap-6">')
        ->toContain('<Table>')
        ->toContain('<Head title="Transactional" />')
        ->toContain("'No transactional emails yet'")
        ->toContain('Compose transactional email')
        ->toMatch('/<TableHead>Status<\/TableHead>\s*<TableHead>Name<\/TableHead>/')
        ->toContain('<TableHead>Subject</TableHead>')
        ->toContain('data-test="transactional-status"')
        ->toContain('data-test="transactional-row"')
        ->toContain('data-test="transactional-name-link"')
        ->toContain('className="font-medium underline-offset-4 hover:underline"')
        ->toContain('href={edit([')
        ->toContain('<div className="flex min-w-0 flex-col">')
        ->toContain('<TableCell className="text-right">')
        ->toContain('<Empty>')
        ->not->toContain("from '@/components/ui/card'")
        ->not->toContain('<Card>')
        ->not->toContain('campaign');
});

test('transactional editor uses the setup hub and fullscreen content editor', function () {
    $editor = file_get_contents(dirname(__DIR__, 2).'/resources/js/pages/transactional/edit.tsx');
    $deleteModal = file_get_contents(dirname(__DIR__, 2).'/resources/js/components/delete-transactional-email-modal.tsx');
    $editorCopy = str_replace(
        [
            "import { CampaignSetupRow } from '@/components/campaign-setup-row';",
            'CampaignSetupRow',
        ],
        '',
        (string) $editor,
    );

    expect($editor)->toBeString()
        ->toContain("'transactional-setup-hub'")
        ->toContain('testId="transactional-setup-details"')
        ->toContain('testId="transactional-setup-sender"')
        ->toContain('testId="transactional-setup-design"')
        ->toContain('testId="transactional-setup-variables"')
        ->toContain("'transactional-design-shell'")
        ->toContain('data-test="transactional-design-navbar"')
        ->toContain('data-test="transactional-design-back"')
        ->toContain('<DialogTitle>Transactional email details</DialogTitle>')
        ->toContain('setLayoutProps({ fullscreen: designing })')
        ->toContain('aria-label="Delete transactional email"')
        ->not->toContain('transactional-tab-')
        ->not->toContain('<Tabs')
        ->and($editorCopy)->not->toContain('campaign')
        ->and($deleteModal)->toBeString()
        ->toContain('<DialogTitle>Delete transactional email</DialogTitle>')
        ->toContain('Delete transactional email')
        ->not->toContain('campaign');
});

test('transactional editor groups secondary actions in an overflow menu', function () {
    $editor = file_get_contents(dirname(__DIR__, 2).'/resources/js/pages/transactional/edit.tsx');

    expect($editor)->toBeString()
        ->toContain('data-test="save-transactional-button"')
        ->toContain('aria-label="Transactional email actions"')
        ->toContain('data-test="transactional-actions-button"')
        ->toContain('data-test="send-test-button"')
        ->toContain("'publish-transactional-button'")
        ->toContain("'unpublish-transactional-button'")
        ->toContain('data-test="delete-transactional-button"')
        ->toMatch('/Delete\\s*<\\/DropdownMenuItem>/');
});

test('transactional editor does not export breadcrumb layout data', function () {
    $editor = file_get_contents(dirname(__DIR__, 2).'/resources/js/pages/transactional/edit.tsx');

    expect($editor)->toBeString()
        ->not->toContain('TransactionalEdit.layout')
        ->not->toContain('breadcrumbs:');
});

test('sidebar lists transactional next to campaigns', function () {
    $sidebar = file_get_contents(dirname(__DIR__, 2).'/resources/js/components/app-sidebar.tsx');

    expect($sidebar)->toBeString()
        ->toContain("title: 'Campaigns'")
        ->toContain("title: 'Transactional'")
        ->toContain("title: 'Templates'")
        ->toContain('MailSend02Icon');
});
