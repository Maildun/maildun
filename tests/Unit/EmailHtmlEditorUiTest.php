<?php

test('html editor uses tabs with desktop and mobile preview widths', function () {
    $editor = file_get_contents(dirname(__DIR__, 2).'/resources/js/components/email-html-editor.tsx');

    expect($editor)->toBeString()
        ->toContain('<Tabs defaultValue="html"')
        ->toContain('<TabsTrigger value="html"')
        ->toContain('<TabsTrigger value="preview"')
        ->toContain('PreviewWidthTabs')
        ->toContain('testIdPrefix="email-preview"')
        ->toContain("'w-[600px]'")
        ->toContain("'w-[375px]'")
        ->not->toContain('ToggleGroup')
        ->not->toContain('ComputerIcon')
        ->not->toContain('lg:grid-cols-2');

    $switch = file_get_contents(dirname(__DIR__, 2).'/resources/js/components/preview-width-tabs.tsx');

    expect($switch)->toBeString()
        ->toContain('variant="sliding"')
        ->toContain("label: 'Desktop'")
        ->toContain("label: 'Mobile'")
        ->not->toContain('HugeiconsIcon');
});
