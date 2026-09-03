<?php

test('current url prefix matching is boundary-aware', function () {
    $root = dirname(__DIR__, 2);
    $helper = file_get_contents($root.'/resources/js/lib/current-url.ts');
    $hook = file_get_contents($root.'/resources/js/hooks/use-current-url.ts');

    expect($helper)->toBeString()
        ->toContain('export function isCurrentPath')
        ->toContain('current.startsWith(`${href}/`)')
        ->not->toContain('urlToCompare.startsWith(path)');

    expect($hook)->toBeString()
        ->toContain('isCurrentPath')
        ->toContain('pathnameFromHref')
        ->not->toContain('urlToCompare.startsWith(path)');
});
