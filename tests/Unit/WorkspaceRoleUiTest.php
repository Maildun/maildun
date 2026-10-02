<?php

test('workspace roles use the main app table treatment and overflow actions', function () {
    $page = file_get_contents(dirname(__DIR__, 2).'/resources/js/pages/teams/roles.tsx');

    expect($page)->toBeString()
        ->toContain('<SettingsPageHeader')
        ->toContain('<Table>')
        ->not->toContain('SettingsPanel')
        ->not->toContain('p-3 sm:p-4')
        ->not->toContain('h-12 px-5')
        ->toContain('MoreHorizontalIcon')
        ->toContain('data-test="workspace-role-actions"')
        ->toContain('data-test="edit-workspace-role"')
        ->toContain('data-test="delete-workspace-role"')
        ->toContain('variant="destructive"')
        ->toContain('!role.is_owner')
        ->toContain('!role.is_system')
        ->not->toContain('aria-label={`Edit ${role.label}`}')
        ->not->toContain('aria-label={`Delete ${role.label}`}');
});
