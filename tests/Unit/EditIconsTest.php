<?php

test('edit actions use Edit03Icon instead of older pencil icons', function () {
    $root = dirname(__DIR__, 2).'/resources/js';
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
    $checked = 0;

    foreach ($iterator as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'tsx') {
            continue;
        }

        $source = file_get_contents($file->getPathname());
        $checked++;

        /** Word-boundary matching so unrelated icons such as MailEdit02Icon are not flagged. */
        expect($source)->toBeString()
            ->not->toMatch('/\bEdit02Icon\b/')
            ->not->toMatch('/\bPencilEdit01Icon\b/')
            ->not->toMatch('/\bPencilEdit02Icon\b/');
    }

    expect($checked)->toBeGreaterThan(0);
});

test('audience table actions use manage and edit icons', function () {
    $source = file_get_contents(dirname(__DIR__, 2).'/resources/js/pages/audiences/index.tsx');

    expect($source)->toBeString()
        ->toContain('data-test="audience-row"')
        ->toContain('<Table>')
        ->toContain('audience.avatar')
        ->toContain('rounded-md after:rounded-md')
        ->not->toContain('rounded-lg after:rounded-lg')
        ->toContain('<HugeiconsIcon icon={UserGroupIcon} />')
        ->toContain('Manage')
        ->toContain('<HugeiconsIcon icon={Edit03Icon} />')
        ->not->toContain('AUDIENCE_GRADIENTS')
        ->not->toContain('audience-card')
        ->not->toContain("from '@/components/ui/card'");
});
