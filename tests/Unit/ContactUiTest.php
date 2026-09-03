<?php

test('the contact dialog uses an email first create or reuse flow', function () {
    $dialog = file_get_contents(dirname(__DIR__, 2).'/resources/js/components/contact-dialog.tsx');

    expect($dialog)
        ->toContain('useHttp')
        ->toContain('lookup.url')
        ->toContain('Existing contacts keep their shared profile')
        ->toContain('Shared name, company, and tags will')
        ->toContain('Use another email')
        ->toContain('audience_uuids')
        ->toContain('Marketing consent confirmed');
});

test('contact and audience pages share the unified add contact dialog', function () {
    $root = dirname(__DIR__, 2).'/resources/js/pages';
    $contacts = file_get_contents($root.'/contacts/index.tsx');
    $audience = file_get_contents($root.'/audiences/show.tsx');

    expect($contacts)
        ->toContain('<ContactDialog')
        ->toContain('audiences={audiences}')
        ->and($audience)
        ->toContain('<ContactDialog')
        ->toContain('defaultAudience={{')
        ->toContain('<SubscriberDialog');
});

test('the contact list previews contacts with profile and edit actions', function () {
    $root = dirname(__DIR__, 2).'/resources/js';
    $page = file_get_contents($root.'/pages/contacts/index.tsx');
    $hoverCard = file_get_contents($root.'/components/contact-hover-card.tsx');

    expect($page)->toBeString()
        ->toContain('ContactHoverCard')
        ->toContain('data-test="contact-row"')
        ->toContain('show.url([teamSlug, contact.uuid])')
        ->toContain('contact={contact}')
        ->toContain('onEdit={canManage ? openEdit : undefined}')
        ->toContain('className="max-w-0"');

    expect($hoverCard)->toBeString()
        ->toContain('data-test="contact-hover-trigger"')
        ->toContain('data-test="contact-hover-card"')
        ->toContain('data-test="contact-hover-profile"')
        ->toContain('data-test="contact-hover-edit"')
        ->toContain('render={<Link href={href} prefetch />}')
        ->toContain('>Company</dt>')
        ->toContain('>Audiences</dt>')
        ->toContain('>Created</dt>')
        ->not->toContain('Edit03Icon');
});

test('contact and audience lists expose the shared background import dialog', function () {
    $root = dirname(__DIR__, 2).'/resources/js';
    $contacts = file_get_contents($root.'/pages/contacts/index.tsx');
    $audience = file_get_contents($root.'/pages/audiences/show.tsx');
    $dialog = file_get_contents($root.'/components/contact-import-dialog.tsx');

    expect($contacts)
        ->toContain('ContactImportDialog')
        ->toContain("pollOnly={['contactImports', 'contacts']}")
        ->toContain('showContactExport.url')
        ->and($audience)
        ->toContain('ContactImportDialog')
        ->toContain("'subscribers'")
        ->toContain('showAudienceExport.url')
        ->and($dialog)
        ->toContain('<DropdownMenuLabel>Import</DropdownMenuLabel>')
        ->toContain('<DropdownMenuLabel>Export</DropdownMenuLabel>')
        ->toContain('MoreHorizontalIcon')
        ->toContain('Xls01Icon')
        ->toContain('aria-label="Import or export contacts"')
        ->toContain('DropdownMenuTrigger')
        ->toContain('onClick={() => setOpen(true)}')
        ->toContain('usePoll')
        ->toContain('duplicate_rows')
        ->toContain('Import');
});
