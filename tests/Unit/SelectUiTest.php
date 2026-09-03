<?php

function selectFile(string $path): string
{
    return file_get_contents(dirname(__DIR__, 2).'/'.$path);
}

test('the shared select maps item values to their labels', function () {
    $select = selectFile('resources/js/components/ui/select.tsx');

    expect($select)->toContain('function collectItems(')
        ->toContain('child.type === SelectItem')
        ->toContain('label: child.props.children')
        ->toContain('items ??')
        ->toContain('items={');
});
