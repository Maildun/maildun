<?php

test('audience deletion confirmation makes the irreversible impact and exact-name safeguard clear', function () {
    $root = dirname(__DIR__, 2).'/resources/js';
    $index = file_get_contents($root.'/pages/audiences/index.tsx');
    $danger = file_get_contents($root.'/pages/audiences/settings/danger.tsx');
    $dialog = file_get_contents($root.'/components/delete-audience-dialog.tsx');

    expect($dialog)->toBeString()
        ->toContain('className="w-lg gap-0 overflow-hidden p-0"')
        ->toContain('icon={AlertCircleIcon}')
        ->toContain('This action permanently removes the audience and')
        ->toContain('It cannot be undone.')
        ->toContain('subscribers_count')
        ->toContain('segments_count')
        ->toContain('forms_count')
        ->toContain('To confirm, type')
        ->toContain('placeholder="Type the audience name"')
        ->toContain('className="px-6 py-6"')
        ->toContain('Delete audience');

    expect($index)->toContain("import { DeleteAudienceDialog } from '@/components/delete-audience-dialog';")
        ->and($danger)->toContain("import { DeleteAudienceDialog } from '@/components/delete-audience-dialog';")
        ->toContain('<DeleteAudienceDialog');
});

test('audience names link to the manage page', function () {
    $index = file_get_contents(dirname(__DIR__, 2).'/resources/js/pages/audiences/index.tsx');

    expect($index)->toBeString()
        ->toContain('data-test="audience-name-link"')
        ->toContain('href={show([')
        ->toContain('currentTeam.slug,')
        ->toContain('audience.uuid,')
        ->toContain('className="font-medium underline-offset-4 hover:underline"');
});
