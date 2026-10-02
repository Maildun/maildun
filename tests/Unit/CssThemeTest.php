<?php

test('theme uses the intended light and dark background colors', function () {
    $root = dirname(__DIR__, 2);
    $css = file_get_contents($root.'/resources/css/app.css');
    $appView = file_get_contents($root.'/resources/views/app.blade.php');

    expect($css)->toBeString();
    expect($appView)->toBeString();

    preg_match('/:root\s*\{([^}]+)\}/', $css, $root);
    preg_match('/\.dark\s*\{([^}]+)\}/', $css, $dark);

    expect($root[1] ?? '')->toContain('--muted: oklch(0.985 0 0)');
    expect($dark[1] ?? '')->toContain('--background: #171717');
    expect($dark[1] ?? '')->toContain('--muted: oklch(0.269 0 0)');
    expect($appView)->toContain('background-color: #171717;');
});

test('titles use Familjen Grotesk as the heading font', function () {
    $root = dirname(__DIR__, 2);
    $css = file_get_contents($root.'/resources/css/app.css');
    $heading = file_get_contents($root.'/resources/js/components/heading.tsx');
    $card = file_get_contents($root.'/resources/js/components/ui/card.tsx');

    expect($css)->toBeString()
        ->toContain("@import '@fontsource-variable/familjen-grotesk'")
        ->toContain("--font-heading: 'Familjen Grotesk Variable', sans-serif")
        ->toContain("--font-sans: 'Inter Variable', sans-serif");

    expect($heading)->toBeString()
        ->toContain('font-heading text-xl font-semibold tracking-tight');

    expect($card)->toBeString()
        ->toContain('font-heading text-base leading-snug font-medium');
});
