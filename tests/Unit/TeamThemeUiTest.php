<?php

test('team theme choices use shadcn selects', function () {
    $themePage = file_get_contents(
        dirname(__DIR__, 2).'/resources/js/pages/teams/theme.tsx',
    );

    expect($themePage)->toBeString()
        ->toContain("from '@/components/ui/select'")
        ->toContain('items={colors}')
        ->toContain('items={fonts}')
        ->toContain('items={inputStyles}')
        ->toContain('<SelectGroup>')
        ->toContain('Button color')
        ->toContain('data-subscribe-form-theme')
        ->toContain('subscribeFormThemeStyle(previewTheme)')
        ->not->toContain('ToggleGroup');
});

test('team theme save tracks controlled select changes as dirty', function () {
    $themePage = file_get_contents(
        dirname(__DIR__, 2).'/resources/js/pages/teams/theme.tsx',
    );

    expect($themePage)->toBeString()
        ->toContain('const hasThemeChanges =')
        ->toContain('const formIsDirty = isDirty || hasThemeChanges;')
        ->toContain('<UnsavedChangesGuard isDirty={formIsDirty} />')
        ->toContain('processing || !formIsDirty');
});
