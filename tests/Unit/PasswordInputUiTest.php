<?php

test('password visibility toggle is keyboard reachable', function () {
    $root = dirname(__DIR__, 2);
    $source = file_get_contents($root.'/resources/js/components/password-input.tsx');

    expect($source)->toBeString()
        ->toContain('aria-label={showPassword ? \'Hide password\' : \'Show password\'}')
        ->toContain('aria-pressed={showPassword}')
        ->not->toContain('tabIndex={-1}');
});
