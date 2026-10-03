<?php

test('email templates are presented as a preview gallery with clear ownership and actions', function () {
    $templates = file_get_contents(dirname(__DIR__, 2).'/resources/js/pages/email-templates/index.tsx');

    expect($templates)->toBeString()
        ->toContain('renderBuilderHtml(')
        ->toContain('toBuilderDocument(template.design)')
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

test('template compose is a setup hub with a fullscreen design canvas', function () {
    $edit = file_get_contents(dirname(__DIR__, 2).'/resources/js/pages/email-templates/edit.tsx');

    expect($edit)->toBeString()
        ->toContain("import { CampaignSetupRow } from '@/components/campaign-setup-row'")
        ->toContain("{ value: 'details', fields: ['name', 'description'] }")
        ->toContain("{ value: 'subject', fields: ['subject', 'preheader'] }")
        ->toContain("{ value: 'design', fields: ['html', 'source', 'design'] }")
        ->toContain('setLayoutProps({ fullscreen: designing })')
        ->toContain('data-test="template-design-navbar"')
        ->toContain('data-test="template-design-rename"')
        ->toContain('data-test="template-design-preview"')
        ->toContain('toBuilderDocument(template.design)')
        ->not->toContain('<Tabs')
        ->not->toContain('breadcrumbs')
        ->not->toContain('Send campaign')
        ->not->toContain('audience');
});
