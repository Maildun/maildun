<?php

/**
 * @return list<string>
 */
function dialogUiTsxFiles(): array
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

test('dialog content starts at w-96 and stays clamped to the viewport', function () {
    $dialog = file_get_contents(dirname(__DIR__, 2).'/resources/js/components/ui/dialog.tsx');

    expect($dialog)
        ->toContain('grid w-96 max-w-[calc(100%-2rem)]')
        ->not->toContain('sm:max-w-md');
});

test('dialogs that need another width override with a w-* class, never max-w-*', function () {
    foreach (dialogUiTsxFiles() as $path) {
        $contents = file_get_contents($path);

        preg_match_all('/<DialogContent\b(.*?)>/s', $contents, $matches);

        foreach ($matches[1] as $props) {
            expect($props)->not->toContain(
                'max-w-',
                "DialogContent in {$path} overrides its width with a max-w-* class. The base class is w-96, "
                .'so a max-w-* override cannot widen the dialog and it also drops the mobile clamp. Use w-* instead.'
            );
        }
    }
});

test('wide dialogs keep their intended width after the w-96 default', function () {
    $root = dirname(__DIR__, 2).'/resources/js';

    expect(file_get_contents($root.'/components/media-detail-dialog.tsx'))->toContain('w-4xl')
        ->and(file_get_contents($root.'/components/delete-audience-dialog.tsx'))->toContain('w-lg')
        ->and(file_get_contents($root.'/components/email-template-picker.tsx'))->toContain('w-2xl')
        ->and(file_get_contents($root.'/components/ui/command.tsx'))->toContain('w-lg')
        ->and(file_get_contents($root.'/pages/audiences/show.tsx'))->toContain('<DialogContent className="w-2xl">')
        ->and(file_get_contents($root.'/pages/segments/show.tsx'))->toContain('<DialogContent className="w-2xl">');
});
