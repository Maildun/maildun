<?php

test('email builder helpers render html with the maildun email builder package', function () {
    $builder = file_get_contents(dirname(__DIR__, 2).'/resources/js/lib/email-builder.ts');

    expect($builder)->toBeString()
        ->toContain("from '@maildun/email-builder'")
        ->toContain("from '@maildun/email-builder/compat'")
        ->toContain('renderEmail(toEmailDocument(document)).html')
        ->toContain('export function toBuilderDocument')
        ->toContain('isEmailBuilderJsDocument(stored)')
        ->toContain('export function renderBuilderHtml')
        ->toContain('export function emptyBuilderDocument')
        ->toContain('export function htmlToBuilderDocument')
        ->toContain('export function sourceToBuilderDocument')
        ->toContain('export function renderSourceHtml')
        ->toContain('export function getChildrenIds')
        ->toContain('export const BUILDER_MERGE_TAGS')
        ->not->toContain('@usewaypoint/email-builder')
        ->not->toContain('MERGE_URL_PREFIX');
});

test('every page that opens a stored design converts legacy documents first', function (string $page) {
    $source = file_get_contents(dirname(__DIR__, 2)."/resources/js/pages/{$page}.tsx");

    expect($source)->toBeString()->toContain('toBuilderDocument(');
})->with(['emails/edit', 'transactional/edit', 'email-templates/edit']);

test('plain text and markdown use the source editor with a live preview', function () {
    $editor = file_get_contents(dirname(__DIR__, 2).'/resources/js/components/email-source-editor.tsx');

    expect($editor)->toBeString()
        ->toContain('EmailSourceMode')
        ->toContain('renderSourceHtml(value, editor)')
        ->toContain('data-test="email-source-input"')
        ->toContain("editor === 'markdown' ? 'Markdown' : 'Plain text'")
        ->toContain('PreviewWidthTabs');
});

test('compose mounts the maildun email builder editor', function () {
    $editor = file_get_contents(dirname(__DIR__, 2).'/resources/js/components/email-builder-editor.tsx');
    $css = file_get_contents(dirname(__DIR__, 2).'/resources/css/app.css');

    expect($editor)->toBeString()
        ->toContain("import { EmailEditor } from '@maildun/email-builder/editor';")
        ->toContain('data-test="email-builder"')
        ->toContain('value={toEmailDocument(document)}')
        ->toContain('readOnly={disabled}')
        ->toContain('mergeTags={mergeTags}');

    expect($css)->toBeString()
        ->toContain("@import '@maildun/email-builder/core.css';")
        ->toContain("@source '../../node_modules/@maildun/email-builder/dist';");

    expect(is_dir(dirname(__DIR__, 2).'/resources/js/email-builder'))->toBeFalse();
});

test('email builder stays contained in the compose card', function () {
    $css = file_get_contents(dirname(__DIR__, 2).'/resources/js/components/email-builder-editor.css');

    expect($css)->toBeString()
        ->toContain('max-width: 100%')
        ->toContain('min-width: 0')
        ->toContain('.email-builder[data-fill]')
        ->toContain('flex: 1 1 0%');
});
