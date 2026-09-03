<?php

test('badge exposes every Tailwind color family with light and dark styles', function () {
    $badge = file_get_contents(dirname(__DIR__, 2).'/resources/js/components/ui/badge.tsx');

    foreach ([
        'slate', 'gray', 'zinc', 'neutral', 'stone', 'red', 'orange', 'amber', 'yellow',
        'lime', 'green', 'emerald', 'teal', 'cyan', 'sky', 'blue', 'indigo', 'violet',
        'purple', 'fuchsia', 'pink', 'rose', 'taupe', 'mauve', 'mist', 'olive',
    ] as $color) {
        expect($badge)->toContain("{$color}:")
            ->toContain("bg-{$color}-50")
            ->toContain("dark:bg-{$color}-400/10");
    }
});
