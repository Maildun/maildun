<?php

/**
 * @return list<string>
 */
function dialogSpacingTsxFiles(): array
{
    $root = dirname(__DIR__, 2).'/resources/js';

    $files = [];

    /** @var SplFileInfo $file */
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root)) as $file) {
        if ($file->isFile() && $file->getExtension() === 'tsx') {
            $files[] = $file->getPathname();
        }
    }

    return $files;
}

test('an Inertia Form wrapping the dialog sections owns its own spacing', function () {
    $seen = 0;

    foreach (dialogSpacingTsxFiles() as $path) {
        $contents = file_get_contents($path);

        preg_match_all('/<DialogContent\b[^>]*>\s*<Form\b([^<]*)/s', $contents, $matches);

        foreach ($matches[1] as $props) {
            $seen++;

            expect(str_contains($props, 'space-y-6') || str_contains($props, 'gap-6'))->toBeTrue(
                "The <Form> in {$path} is the only direct child of its <DialogContent>, so the dialog's "
                .'grid gap-6 never separates the header, fields, and footer. Give the Form className="space-y-6".'
            );
        }
    }

    expect($seen)->toBeGreaterThanOrEqual(9);
});

test('dialog forms pair their own spacing with a tightened field group', function () {
    $root = dirname(__DIR__, 2).'/resources/js';

    foreach ([
        'components/tag-dialog.tsx',
        'components/send-test-email-dialog.tsx',
        'components/send-test-transactional-email-dialog.tsx',
        'components/create-automation-dialog.tsx',
        'pages/audiences/settings/attributes.tsx',
        'pages/teams/api.tsx',
    ] as $page) {
        $source = file_get_contents($root.'/'.$page);

        expect($source)->toBeString()
            ->toContain('space-y-6')
            ->toContain('<FieldGroup className="gap-5">')
            ->not->toContain('<FieldGroup className="py-4">');
    }
});

test('the transactional test dialog keeps its controls visible when variables overflow', function () {
    $source = file_get_contents(
        dirname(__DIR__, 2).'/resources/js/components/send-test-transactional-email-dialog.tsx'
    );

    expect($source)->toBeString()
        ->toContain('max-h-[calc(100svh-2rem)]')
        ->toContain('className="flex min-h-0 flex-col space-y-6 overflow-hidden"')
        ->toContain('className="flex min-h-0 flex-1 flex-col gap-5"')
        ->toContain('aria-label="Merge tag sample values"')
        ->toContain('overflow-y-auto overscroll-contain')
        ->toContain('<DialogFooter className="shrink-0 gap-2">');
});
