<?php

test('app sidebar pages use a max-w-7xl container', function () {
    $layout = file_get_contents(
        dirname(__DIR__, 2).'/resources/js/layouts/app/app-sidebar-layout.tsx',
    );

    expect($layout)->toBeString()
        ->toContain("'min-h-0 min-w-0 overflow-x-hidden'")
        ->toContain('mx-auto flex min-h-0 w-full max-w-7xl min-w-0 flex-1 flex-col')
        ->toContain('flex min-h-0 min-w-0 flex-1 flex-col p-10')
        ->not->toContain('max-w-5xl');
});

test('fullscreen mode preserves the layout tree and mounted page state', function () {
    $layout = file_get_contents(
        dirname(__DIR__, 2).'/resources/js/layouts/app/app-sidebar-layout.tsx',
    );

    expect($layout)->toBeString()
        ->toContain('fullscreen = false')
        ->toContain('<AppShell variant="sidebar">')
        ->toContain("fullscreen ? 'hidden' : 'contents'")
        ->toContain('fixed inset-0 z-50 h-dvh overflow-hidden bg-background')
        ->toContain("fullscreen && 'h-dvh max-w-none'")
        ->toContain("fullscreen && 'p-0'")
        ->not->toContain('if (fullscreen)');
});
