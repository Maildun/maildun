<?php

test('sidebar lists media after templates', function () {
    $sidebar = file_get_contents(dirname(__DIR__, 2).'/resources/js/components/app-sidebar.tsx');

    expect($sidebar)->toBeString()
        ->toContain("title: 'Templates'")
        ->toContain("title: 'Media'")
        ->toContain('FolderLibraryIcon')
        ->toMatch("/title: 'Templates'[\s\S]*title: 'Media'/");
});

test('media index is a thumbnail grid with upload and settings controls', function () {
    $index = file_get_contents(dirname(__DIR__, 2).'/resources/js/pages/media/index.tsx');
    $activeFilters = file_get_contents(dirname(__DIR__, 2).'/resources/js/components/active-filters.tsx');
    $dropzone = file_get_contents(dirname(__DIR__, 2).'/resources/js/components/media-dropzone.tsx');
    $dialog = file_get_contents(dirname(__DIR__, 2).'/resources/js/components/media-detail-dialog.tsx');
    $categoryCombobox = file_get_contents(dirname(__DIR__, 2).'/resources/js/components/media-category-combobox.tsx');

    expect($index)->toBeString()
        ->toContain('<div className="flex min-h-0 flex-1 flex-col gap-6">')
        ->toContain('<Head title="Media" />')
        ->toContain('<EmptyTitle>No media yet</EmptyTitle>')
        ->toContain('data-test="media-tile"')
        ->toContain('data-test="upload-media-button"')
        ->toContain('icon={ArrowUp03Icon}')
        ->not->toContain('icon={ImageAdd01Icon}')
        ->toContain('data-test="media-settings-button"')
        ->toContain('data-test="media-file-input"')
        ->toContain('testId="media-filter-button"')
        ->toContain("testId: 'media-category-filter'")
        ->toContain("testId: 'media-tag-filter'")
        ->toContain('multiple')
        ->toContain('Drop images here or click to browse')
        ->toContain('No files match those filters.')
        ->toContain('All categories')
        ->toContain('Uncategorized')
        ->toContain('All tags')
        ->toContain('clearTestId="clear-media-filters"')
        ->toContain("field: 'Category'")
        ->toContain("field: 'Tag'")
        ->toContain('grid-cols-2')
        ->toContain('rounded-3xl')
        ->toContain('rounded-2xl')
        ->toContain('aspect-video')
        ->toContain('usePoll(')
        ->toContain('placeholder="Search media"')
        ->toContain('min-h-0')
        ->not->toContain('bg-muted/40')
        ->not->toContain('<Table>')
        ->and($activeFilters)->toBeString()
        ->toContain('bg-muted')
        ->toContain('Clear Filters')
        ->toContain('<Kbd>Esc</Kbd>')
        ->toContain('export function ActiveFilters')
        ->toContain('Cancel01Icon')
        ->not->toContain('icon={icon}')
        ->and($dropzone)->toBeString()
        ->toContain('data-test="media-dropzone"')
        ->toContain('overflow-auto p-2')
        ->toContain('Drop images to upload')
        ->toContain('Up to 20 images, 2 MB each')
        ->toContain('onDrop')
        ->toContain('{dragging &&')
        ->toContain('border-primary')
        ->toContain('bg-primary/30')
        ->toContain('text-primary')
        ->not->toContain('bg-muted/40')
        ->not->toContain('min-h-full')
        ->and($dialog)->toBeString()
        ->toContain('<DialogContent')
        ->toContain('w-4xl')
        ->toContain('md:grid-cols-2')
        ->toContain('Search or create a tag…')
        ->toContain('<FieldLabel htmlFor="media-category">')
        ->toContain('<FieldLabel htmlFor="media-url">')
        ->toContain('data-test="copy-media-link"')
        ->toContain('data-test="download-media-button"')
        ->toContain('data-test="media-actions-button"')
        ->toContain('data-test="delete-media-button"')
        ->toContain('ArrowDown03Icon')
        ->toContain('MoreHorizontalIcon')
        ->toContain('Delete02Icon')
        ->toContain('Copy link')
        ->toContain('Download')
        ->toContain('form="media-details-form"')
        ->not->toContain('Sheet')
        ->not->toContain('>Delete</Button>')
        ->and($categoryCombobox)->toBeString()
        ->toContain('Search or create a category…');
});

test('media uploads enforce the file limit before starting the progress toast', function () {
    $hook = file_get_contents(dirname(__DIR__, 2).'/resources/js/hooks/use-media-upload.ts');

    expect($hook)->toBeString()
        ->toContain('export const MEDIA_MAX_BYTES = 2 * 1024 * 1024;')
        ->toContain('file.size > MEDIA_MAX_BYTES')
        ->toContain('Each image must be 2 MB or smaller.')
        ->toContain('uploadToast.begin')
        ->toContain('uploadToast.setProgress')
        ->toContain('Uploading image…')
        ->toContain('Image uploaded.')
        ->toContain('JPEG and PNG are being optimized.')
        ->toContain('Failed to upload media.');
});

test('media settings live in the media manager not account settings', function () {
    $settings = file_get_contents(dirname(__DIR__, 2).'/resources/js/pages/media/settings.tsx');
    $app = file_get_contents(dirname(__DIR__, 2).'/resources/js/app.tsx');

    expect($settings)->toBeString()
        ->toContain('Convert JPG and PNG to WebP')
        ->toContain('data-test="convert-uploads-switch"')
        ->toContain('Back to media')
        ->toContain('<SettingsPanel')
        ->and($app)->toBeString()
        ->toContain("case name.startsWith('settings/'):")
        ->toContain("case name.startsWith('teams/'):")
        ->not->toContain("case name.startsWith('media/')");
});
