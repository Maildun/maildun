<?php

/*
 * Postgres compares LIKE case-sensitively, while SQLite, which the tests run on,
 * ignores case for ASCII. A search written with a plain 'like' therefore passes
 * every test yet can't find "Newsletter" for "newsletter" in production.
 * Searches use whereLike(), which ignores case on every database.
 */
test('text searches never use a case-sensitive like', function () {
    $root = dirname(__DIR__, 2);
    $allowed = [
        // Slugs are always lowercase; this matches numbered copies, not a search.
        $root.'/app/Concerns/GeneratesUniqueTeamSlugs.php',
    ];
    $checked = 0;
    $offenders = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/app'));

    foreach ($iterator as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php' || in_array($file->getPathname(), $allowed, true)) {
            continue;
        }

        $checked++;

        foreach ((array) file($file->getPathname()) as $index => $line) {
            if (preg_match('/,\s*[\'"]like[\'"]\s*,/i', (string) $line) === 1) {
                $offenders[] = str_replace($root.'/', '', $file->getPathname()).':'.($index + 1);
            }
        }
    }

    expect($checked)->toBeGreaterThan(0)
        ->and($offenders)->toBeEmpty();
});
