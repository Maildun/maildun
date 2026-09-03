<?php

test('the tags settings table uses a rounded square swatch, row dropdown, and select all', function () {
    $root = dirname(__DIR__, 2);
    $page = file_get_contents($root.'/resources/js/pages/teams/tags.tsx');
    $swatch = file_get_contents($root.'/resources/js/components/tag-color-select.tsx');
    $select = file_get_contents($root.'/resources/js/components/ui/select.tsx');
    $modal = file_get_contents($root.'/resources/js/components/delete-tag-modal.tsx');

    expect($swatch)->toBeString()
        ->toContain('rounded-[2px]')
        ->not->toContain('rounded-full');

    expect($select)->toBeString()
        ->toContain('className="flex flex-1 shrink-0 items-center gap-2 whitespace-nowrap"');

    expect($page)->toBeString()
        ->toContain('Create tag')
        ->toContain('aria-label="Select all"')
        ->toContain('data-test="tag-select-all"')
        ->toContain('data-test="tag-row-select"')
        ->toContain('data-test="delete-selected-tags"')
        ->toContain('data-test="tag-actions"')
        ->toContain('DropdownMenu')
        ->toContain('MoreHorizontalIcon')
        ->toContain('data-test="edit-tag-button"')
        ->toContain('data-test="delete-tag-button"')
        ->toContain('<div className="p-3 sm:p-4">')
        ->toContain('className="h-12 px-5"')
        ->toContain('className="h-16"')
        ->toContain('className="max-w-0 px-5 py-4"')
        ->not->toContain('tagColorLabel')
        ->not->toContain('formatRelativeTime')
        ->not->toContain('<Badge variant="secondary">')
        ->not->toContain('Tooltip')
        ->not->toContain('New tag');

    expect($modal)->toBeString()
        ->toContain('bulkDestroy')
        ->toContain('Delete tags')
        ->toContain('data-test="delete-tag-confirm"');
});
