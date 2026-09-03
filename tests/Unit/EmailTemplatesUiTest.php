<?php

test('email templates are presented as a preview gallery with clear ownership and actions', function () {
    $templates = file_get_contents(dirname(__DIR__, 2).'/resources/js/pages/email-templates/index.tsx');

    expect($templates)->toBeString()
        ->toContain("import { Reader } from '@usewaypoint/email-builder';")
        ->toContain('function TemplatePreview')
        ->toContain('function ScaledEmailFrame')
        ->toContain('justify-center')
        ->toContain('origin-top')
        ->toContain('data-test="email-template-preview"')
        ->toContain('sandbox=""')
        ->toContain('<Head title="Templates" />')
        ->toContain('Templates')
        ->not->toContain('className="size-6 text-muted-foreground"')
        ->toContain('New template')
        ->toContain('sm:grid-cols-2 lg:grid-cols-3')
        ->toContain('rounded-2xl bg-muted')
        ->toContain('overflow-hidden rounded-t-xl border border-b-0 bg-background')
        ->toContain('h-[calc(100%+1.5rem)]')
        ->toContain('items-start')
        ->toContain('group-hover:shadow-md')
        ->toContain('transition-shadow')
        ->toContain('duration-300')
        ->toContain('motion-reduce:transition-none')
        ->toContain('galleryUpdatedLabel')
        ->toContain('Switch the team editor to use this template.')
        ->toContain('data-test="use-template-button"')
        ->toContain('setTemplateToUse(template)')
        ->toContain('data-test="edit-template-button"')
        ->toContain('CreateEmailTemplateDialog')
        ->not->toContain("from '@/components/email-template-dialog'");

    $picker = file_get_contents(dirname(__DIR__, 2).'/resources/js/components/email-template-picker.tsx');

    expect($picker)->toBeString()
        ->toContain('templateIsLocked')
        ->toContain('Name the campaign to start from');
});

test('template compose is a full page with details and content tabs', function () {
    $edit = file_get_contents(dirname(__DIR__, 2).'/resources/js/pages/email-templates/edit.tsx');

    expect($edit)->toBeString()
        ->toContain("value: 'details'")
        ->toContain("value: 'content'")
        ->toContain("fields: ['name', 'description', 'subject', 'preheader']")
        ->toContain("fields: ['html', 'source', 'design']")
        ->toContain('variant="sliding"')
        ->toContain('Compose the email campaigns will start from.')
        ->not->toContain('Send campaign')
        ->not->toContain('audience');
});
