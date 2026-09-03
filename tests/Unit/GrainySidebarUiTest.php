<?php

test('app css defines a grainy noise utility with light and dark strengths', function () {
    $css = file_get_contents(dirname(__DIR__, 2).'/resources/css/app.css');

    expect($css)->toBeString()
        ->toContain('@utility grainy')
        ->toContain('feTurbulence')
        ->toContain("type='fractalNoise'")
        ->toContain('opacity: var(--grain-opacity)');

    preg_match('/:root\s*\{([^}]+)\}/', $css, $root);
    preg_match('/\.dark\s*\{([^}]+)\}/', $css, $dark);

    expect($root[1] ?? '')->toContain('--grain-opacity: 0.18');
    expect($dark[1] ?? '')->toContain('--grain-opacity: 0.28');
});

test('every sidebar surface carries the grain overlay', function () {
    $root = dirname(__DIR__, 2);
    $sidebar = file_get_contents($root.'/resources/js/components/ui/sidebar.tsx');
    $header = file_get_contents($root.'/resources/js/components/app-header.tsx');

    // Desktop panel, static sidebar (collapsible="none"), and the mobile sheet.
    expect($sidebar)->toBeString()
        ->toContain('"relative flex size-full grainy flex-col bg-muted/50')
        ->toContain('"relative flex h-full w-(--sidebar-width) grainy flex-col bg-muted/50')
        ->toContain('className="w-(--sidebar-width) grainy bg-muted/50');

    // Header layout's mobile nav drawer.
    expect($header)->toBeString()
        ->toContain('className="grainy flex h-full w-64 flex-col items-stretch justify-between bg-sidebar"');
});

test('the primary button carries the grain overlay', function () {
    $button = file_get_contents(dirname(__DIR__, 2).'/resources/js/components/ui/button.tsx');

    expect($button)->toBeString()
        ->toContain('"relative grainy bg-primary text-primary-foreground');
});
