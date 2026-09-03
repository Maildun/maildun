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
