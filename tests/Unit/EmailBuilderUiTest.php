<?php

test('email builder helpers render html from the stock document format', function () {
    $builder = file_get_contents(dirname(__DIR__, 2).'/resources/js/lib/email-builder.ts');

    expect($builder)->toBeString()
        ->toContain('export const ROOT_BLOCK_ID = \'root\'')
        ->toContain('export function renderBuilderHtml')
        ->toContain('export function emptyBuilderDocument')
        ->toContain('export function htmlToBuilderDocument')
        ->toContain('export function sourceToBuilderDocument')
        ->toContain('export function renderSourceHtml')
        ->toContain('export function getChildrenIds')
        ->not->toContain('export function duplicateBlock')
        ->not->toContain('BUILDER_BLOCK_LABELS');
});

test('plain text and markdown use the source editor with a live preview', function () {
    $editor = file_get_contents(dirname(__DIR__, 2).'/resources/js/components/email-source-editor.tsx');

    expect($editor)->toBeString()
        ->toContain('EmailSourceMode')
        ->toContain('sourceToBuilderDocument')
        ->toContain('<Reader')
        ->toContain('data-test="email-source-input"')
        ->toContain("editor === 'markdown' ? 'Markdown' : 'Plain text'")
        ->toContain('PreviewWidthTabs');
});

test('compose uses the vendored emailbuilder js editor rather than a custom block ui', function () {
    $editor = file_get_contents(dirname(__DIR__, 2).'/resources/js/components/email-builder-editor.tsx');
    $app = file_get_contents(dirname(__DIR__, 2).'/resources/js/email-builder/App/index.tsx');
    $core = file_get_contents(dirname(__DIR__, 2).'/resources/js/email-builder/documents/editor/core.tsx');

    expect($editor)->toBeString()
        ->toContain("from '@/email-builder/App'")
        ->toContain('ThemeProvider')
        ->toContain('data-test="email-builder-js"')
        ->toContain('resetDocument')
        ->toContain('subscribeDocument')
        ->not->toContain('email-builder-fields');

    expect($app)->toBeString()
        ->toContain('InspectorDrawer')
        ->toContain('TemplatePanel')
        ->not->toContain('SamplesDrawer');

    expect($core)->toBeString()
        ->toContain('Avatar:')
        ->toContain('Button:')
        ->toContain('Container:')
        ->toContain('ColumnsContainer:')
        ->toContain('Heading:')
        ->toContain('Html:')
        ->toContain('Image:')
        ->toContain('Text:')
        ->toContain('EmailLayout:')
        ->toContain('Spacer:')
        ->toContain('Divider:');

    expect(file_exists(dirname(__DIR__, 2).'/resources/js/components/email-builder-fields.tsx'))->toBeFalse();
});

test('email builder does not mount the samples drawer', function () {
    $app = file_get_contents(dirname(__DIR__, 2).'/resources/js/email-builder/App/index.tsx');
    $panel = file_get_contents(dirname(__DIR__, 2).'/resources/js/email-builder/App/TemplatePanel/index.tsx');
    $css = file_get_contents(dirname(__DIR__, 2).'/resources/js/components/email-builder-editor.css');

    expect($app)->toBeString()
        ->not->toContain('SamplesDrawer')
        ->not->toContain('useSamplesDrawerOpen');

    expect($panel)->toBeString()
        ->not->toContain('ToggleSamplesPanelButton')
        ->toContain('ToggleInspectorPanelButton');

    expect($css)->toBeString()
        ->toContain('.MuiDrawer-paperAnchorRight')
        ->toContain('right: 0')
        ->not->toContain('.MuiDrawer-paperAnchorLeft');

    expect(is_dir(dirname(__DIR__, 2).'/resources/js/email-builder/App/SamplesDrawer'))->toBeFalse();
});

test('email builder stays contained on narrow screens', function () {
    $css = file_get_contents(dirname(__DIR__, 2).'/resources/js/components/email-builder-editor.css');

    expect($css)->toBeString()
        ->toContain('container-type: inline-size')
        ->toContain('max-width: 100%')
        ->toContain('min-width: 0')
        ->toContain('@container (max-width: 43rem)')
        ->toContain('margin-right: 0 !important')
        ->toContain('.email-builder-js[data-fill]')
        ->toContain('flex: 1 1 0%');
});
