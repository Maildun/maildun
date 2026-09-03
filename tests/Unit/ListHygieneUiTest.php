<?php

test('list hygiene follows the plain table presentation', function () {
    $index = file_get_contents(dirname(__DIR__, 2).'/resources/js/pages/list-hygiene/index.tsx');

    expect($index)->toBeString()
        ->toContain('<Table>')
        ->toContain('title={`${copy.label} subscribers`}')
        ->toContain('variant="small"')
        ->toContain('className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"')
        ->not->toContain("import { SettingsPanel } from '@/components/settings-panel';")
        ->not->toContain('<SettingsPanel');
});
