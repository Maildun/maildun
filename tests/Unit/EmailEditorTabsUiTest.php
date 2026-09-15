<?php

test('campaign draft compose is a setup hub instead of seven tabs', function () {
    $source = file_get_contents(dirname(__DIR__, 2).'/resources/js/pages/emails/edit.tsx');

    expect($source)->toBeString()
        ->toContain("import { CampaignSetupRow } from '@/components/campaign-setup-row'")
        ->toContain('testId="campaign-setup-sender"')
        ->toContain('testId="campaign-setup-recipients"')
        ->toContain('testId="campaign-setup-subject"')
        ->toContain('testId="campaign-setup-design"')
        ->toContain('testId="campaign-setup-settings"')
        ->toContain('Manage sender')
        ->toContain('Add recipients')
        ->toContain('Add subject')
        ->toContain('Start designing')
        ->toContain('Edit design')
        ->toContain('Edit settings')
        ->toContain("view === 'design'")
        ->toContain('data-test="campaign-design-preview"')
        ->toContain('<Reader')
        ->toContain('toReaderDocument')
        ->not->toContain('<TabsList')
        ->not->toContain('email-tab-')
        ->not->toContain('<SettingsPanel')
        ->not->toContain("from '@/components/ui/tabs'");
});

test('campaign draft header truncates long titles without shrinking its controls', function () {
    $source = file_get_contents(dirname(__DIR__, 2).'/resources/js/pages/emails/edit.tsx');

    expect($source)->toBeString()
        ->toContain('flex min-w-0 flex-1 items-center gap-2')
        ->toContain('min-w-0 flex-1 truncate text-xl font-semibold tracking-tight')
        ->toContain('flex flex-wrap items-center justify-end gap-2 sm:shrink-0 sm:flex-nowrap')
        ->toContain("'shrink-0'")
        ->toContain('className="shrink-0"');
});

test('campaign delivery opens one dedicated recipient-personalized preview page', function () {
    $source = file_get_contents(dirname(__DIR__, 2).'/resources/js/pages/emails/edit.tsx');
    $preview = file_get_contents(dirname(__DIR__, 2).'/resources/js/pages/emails/preview-and-send.tsx');

    expect($source)->toBeString()
        ->toContain('previewAndSend')
        ->toContain('Preview and Send')
        ->toContain('data-test="preview-and-send-button"')
        ->not->toContain('CampaignSendPreviewDialog')
        ->not->toContain('SendTestEmailDialog')
        ->not->toContain('data-test="send-test-button"')
        ->not->toContain('data-test="send-campaign-button"');

    expect($preview)->toBeString()
        ->toContain('setLayoutProps({ fullscreen: true })')
        ->toContain('data-test="preview-and-send-page"')
        ->toContain('data-test="campaign-preview-toolbar"')
        ->toContain('<PreviewWidthTabs')
        ->toContain('data-test="campaign-preview-zoom"')
        ->toContain('data-test="campaign-preview-recipient"')
        ->toContain('data-test="campaign-preview-previous-recipient"')
        ->toContain('data-test="campaign-preview-next-recipient"')
        ->toContain('data-test="campaign-recipient-preview"')
        ->toContain('data-test="campaign-preview-canvas"')
        ->toContain('mx-auto min-h-0 flex-1 overflow-auto rounded-lg border bg-background shadow-sm transition-[width] duration-200')
        ->toContain('sandbox="allow-same-origin"')
        ->toContain('pointer-events-none block origin-top-left border-0 bg-background')
        ->toContain('previewDocumentHeight * previewScale')
        ->toContain('measurePreviewDocumentHeight')
        ->toContain('contentDocument')
        ->toContain('composePreview.url')
        ->toContain('data-test="confirm-send-campaign"')
        ->not->toContain('h-[calc(100dvh-9rem)]')
        ->not->toContain('height: `${100 / previewScale}%`')
        ->not->toContain('PREVIEW_HEIGHT');
});

test('campaign draft sections map every validated field for error routing', function () {
    $source = file_get_contents(dirname(__DIR__, 2).'/resources/js/pages/emails/edit.tsx');

    expect($source)->toBeString()
        ->toContain("value: 'name', fields: ['name']")
        ->toContain("value: 'sender',\n        fields: ['sender_uuid']")
        ->toContain("value: 'recipients', fields: ['audience', 'segment']")
        ->toContain("value: 'subject', fields: ['subject', 'preheader']")
        ->toContain("value: 'design', fields: ['html', 'source', 'design']")
        ->toContain("'plain_text'")
        ->toContain("'query_string'")
        ->toContain("'track_clicks'")
        ->toContain("'track_opens'")
        ->toContain("'attachments'")
        ->toContain('revealSection(firstSection.value)');
});

test('campaign sender uses a verified sender select with the audience default', function () {
    $source = file_get_contents(dirname(__DIR__, 2).'/resources/js/pages/emails/edit.tsx');

    expect($source)->toBeString()
        ->toContain('data-test="campaign-sender-select"')
        ->toContain("const AUDIENCE_DEFAULT_SENDER = 'audience-default'")
        ->toContain('Use the audience sender by default')
        ->toContain('Manage senders')
        ->not->toContain('<FieldLabel htmlFor="from_name">')
        ->not->toContain('<FieldLabel htmlFor="from_address">')
        ->not->toContain('<FieldLabel htmlFor="reply_to">');
});

test('campaign design view fills leftover height and hub dialogs use the default width', function () {
    $source = file_get_contents(dirname(__DIR__, 2).'/resources/js/pages/emails/edit.tsx');
    $row = file_get_contents(dirname(__DIR__, 2).'/resources/js/components/campaign-setup-row.tsx');
    $css = file_get_contents(dirname(__DIR__, 2).'/resources/js/components/email-builder-editor.css');

    expect($source)->toBeString()
        ->toContain('setLayoutProps({ fullscreen: designing })')
        ->toContain("'campaign-design-shell'")
        ->toContain('data-test="campaign-design-navbar"')
        ->toContain('data-test="campaign-design-back"')
        ->toContain('data-test="open-media-library"')
        ->toContain('Open media')
        ->toContain('relative flex h-dvh min-h-0 w-full flex-col overflow-hidden bg-background')
        ->toContain('fill')
        ->toContain('<DialogContent>')
        ->not->toContain('w-2xl')
        ->not->toContain('max-w-96')
        ->toContain("subjectDone &&\n        hasBody &&\n        form.data.audience !== null &&\n        recipientCount > 0 &&\n        !form.isDirty")
        ->not->toContain('min-h-full min-w-0 flex-col gap-4')
        ->and($row)->toContain('CheckmarkCircleSolidIcon')
        ->toContain('text-success')
        ->and($css)->toContain('.email-builder-js[data-fill]')
        ->toContain('flex: 1 1 0%');
});

test('campaign media dialog supports upload search and copy link', function () {
    $source = file_get_contents(dirname(__DIR__, 2).'/resources/js/components/media-library-dialog.tsx');

    expect($source)->toBeString()
        ->toContain('data-test="builder-media-dialog"')
        ->toContain('data-test="upload-builder-media"')
        ->toContain('data-test="builder-media-file-input"')
        ->toContain('data-test="copy-builder-media-link"')
        ->toContain("only: ['mediaLibrary']")
        ->toContain('Search recent media')
        ->toContain('EmailBuilder.js');
});

test('campaign draft saves each section and keeps template creation in campaign actions', function () {
    $source = file_get_contents(dirname(__DIR__, 2).'/resources/js/pages/emails/edit.tsx');

    expect(substr_count((string) $source, 'data-test="save-email-button"'))->toBe(1)
        ->and(substr_count((string) $source, 'Save as template'))->toBe(1)
        ->and(substr_count((string) $source, 'onClick={saveSection}'))->toBe(5);

    expect($source)->toBeString()
        ->toContain('saveDraft(() => setOpenSection(null))')
        ->toContain('form.setDefaults()')
        ->not->toContain('aria-label="Design actions"')
        ->not->toMatch('/>\s*Done\s*</');
});

test('the sliding tab indicator stays aligned when the tab list scrolls', function () {
    $source = file_get_contents(dirname(__DIR__, 2).'/resources/js/components/ui/tabs.tsx');

    expect($source)->toBeString()
        ->toContain('triggerRect.left - listRect.left + list.scrollLeft')
        ->toContain('inline-flex w-fit shrink-0 items-center');
});
