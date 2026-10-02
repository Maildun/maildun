<?php

test('audience pages expose copyable audience IDs', function () {
    $root = dirname(__DIR__, 2).'/resources/js/pages/audiences';
    $show = file_get_contents($root.'/show.tsx');
    $settings = file_get_contents($root.'/edit.tsx');

    expect($show)->toBeString()
        ->toContain('data-test="copy-audience-id"')
        ->toContain('copy(audience.uuid)')
        ->toContain("'Copy audience ID'");

    expect($settings)->toBeString()
        ->toContain('Audience ID')
        ->toContain('{audience.uuid}')
        ->toContain('data-test="copy-audience-id-settings"')
        ->toContain('copy(audience.uuid)')
        ->toContain("'Copy ID'");
});
