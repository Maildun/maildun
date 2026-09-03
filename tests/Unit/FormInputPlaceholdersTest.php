<?php

test('form inputs have placeholders', function () {
    $root = dirname(__DIR__, 2).'/resources/js';
    $skip = [
        $root.'/components/ui/input.tsx',
        $root.'/components/ui/textarea.tsx',
        $root.'/components/ui/input-group.tsx',
        $root.'/components/ui/input-otp.tsx',
        $root.'/components/ui/combobox.tsx',
        $root.'/components/ui/code-editor.tsx',
        $root.'/components/ui/sidebar.tsx',
        $root.'/components/password-input.tsx',
    ];
    $names = [
        'InputGroupInput',
        'InputGroupTextarea',
        'PasswordInput',
        'ComboboxChipsInput',
        'Textarea',
        'Input',
    ];

    $checked = 0;
    $missing = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

    foreach ($iterator as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'tsx') {
            continue;
        }

        $path = $file->getPathname();

        if (in_array($path, $skip, true)) {
            continue;
        }

        $source = file_get_contents($path);
        expect($source)->toBeString();

        foreach ($names as $name) {
            foreach (formInputOpeningTags($source, $name) as [$line, $attrs]) {
                if (str_contains($attrs, 'type="color"') || str_contains($attrs, "type='color'")) {
                    continue;
                }

                $checked++;

                if (! str_contains($attrs, 'placeholder=')) {
                    $relative = str_replace(dirname(__DIR__, 2).'/', '', $path);
                    $missing[] = "{$relative}:{$line} <{$name}>";
                }
            }
        }
    }

    expect($checked)->toBeGreaterThan(0);
    expect($missing)->toBeEmpty();
});

test('password fields use PasswordInput', function () {
    $root = dirname(__DIR__, 2).'/resources/js';
    $skip = $root.'/components/password-input.tsx';
    $checked = 0;
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

    foreach ($iterator as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'tsx') {
            continue;
        }

        $path = $file->getPathname();

        if ($path === $skip) {
            continue;
        }

        $source = file_get_contents($path);
        $checked++;

        expect($source)->toBeString()
            ->not->toContain('type="password"')
            ->not->toContain("type='password'");
    }

    expect($checked)->toBeGreaterThan(0);
});

/**
 * @return list<array{int, string}>
 */
function formInputOpeningTags(string $source, string $name): array
{
    $results = [];
    $needle = '<'.$name;
    $offset = 0;

    while (($index = strpos($source, $needle, $offset)) !== false) {
        $after = $index + strlen($needle);

        if (isset($source[$after]) && (ctype_alnum($source[$after]) || $source[$after] === '_')) {
            $offset = $after;

            continue;
        }

        $depth = 0;
        $quote = null;
        $end = $after;
        $length = strlen($source);

        while ($end < $length) {
            $char = $source[$end];

            if ($quote !== null) {
                if ($char === $quote && ($end === 0 || $source[$end - 1] !== '\\')) {
                    $quote = null;
                }
            } elseif ($char === '"' || $char === "'" || $char === '`') {
                $quote = $char;
            } elseif ($char === '{') {
                $depth++;
            } elseif ($char === '}') {
                $depth--;
            } elseif ($char === '>' && $depth === 0) {
                break;
            }

            $end++;
        }

        $attrs = substr($source, $after, $end - $after);
        $line = substr_count(substr($source, 0, $index), "\n") + 1;
        $results[] = [$line, $attrs];
        $offset = $end + 1;
    }

    return $results;
}
