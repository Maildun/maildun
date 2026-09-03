<?php

test('Vite defaults to free Hugeicons while supporting licensed Pro styles', function () {
    $root = dirname(__DIR__, 2);
    $viteConfig = file_get_contents($root.'/vite.config.ts');
    $environmentExample = file_get_contents($root.'/.env.example');
    $npmrc = file_get_contents($root.'/.npmrc');

    expect($viteConfig)->toBeString()
        ->toContain("'free-stroke-rounded': '@hugeicons/core-free-icons'")
        ->toContain("'pro-stroke-rounded': '@hugeicons-pro/core-stroke-rounded'")
        ->toContain("'pro-stroke-standard': '@hugeicons-pro/core-stroke-standard'")
        ->toContain('HUGEICONS_ICON_STYLE')
        ->toContain('find: /^@hugeicons\\/core-free-icons$/')
        ->toContain("noExternal: ['@hugeicons/core-free-icons', iconPackage]");

    expect($environmentExample)
        ->toBeString()
        ->toContain('HUGEICONS_ICON_STYLE=free-stroke-rounded');

    expect($npmrc)
        ->toBeString()
        ->not->toContain('_authToken');
});
