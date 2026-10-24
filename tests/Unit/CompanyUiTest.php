<?php

test('company actions use text labels without icons', function () {
    $page = file_get_contents(
        dirname(__DIR__, 2).'/resources/js/pages/companies/show.tsx',
    );

    expect($page)->toBeString()
        ->toContain('onClick={openEdit}')
        ->toContain('onClick={() => setDeleteOpen(true)}')
        ->toMatch('/onClick=\{openEdit\}\s*>\s*Edit\s*<\/Button>/')
        ->toMatch('/onClick=\{\(\) => setDeleteOpen\(true\)\}\s*>\s*Delete\s*<\/Button>/')
        ->not->toContain('Edit03Icon')
        ->not->toContain('Delete02Icon');
});

test('company logos render with padding on white backgrounds', function () {
    $indexPage = file_get_contents(
        dirname(__DIR__, 2).'/resources/js/pages/companies/index.tsx',
    );
    $showPage = file_get_contents(
        dirname(__DIR__, 2).'/resources/js/pages/companies/show.tsx',
    );

    expect($indexPage)->toBeString()
        ->toContain('className="size-4 rounded-sm bg-white object-contain p-0.5"');
    expect($showPage)->toBeString()
        ->toContain('className="size-12 rounded-lg bg-white object-contain p-1.5"');
});

test('company contacts use the shared contact hover card', function () {
    $page = file_get_contents(
        dirname(__DIR__, 2).'/resources/js/pages/companies/show.tsx',
    );

    expect($page)->toBeString()
        ->toContain('CompanyContactRow')
        ->toContain('ContactHoverCard')
        ->toContain('data-test="company-contact-row"')
        ->toContain('showContact.url([teamSlug, contact.uuid])')
        ->toContain('onEdit={canManageContacts ? openEdit : undefined}')
        ->toContain('<ContactDialog')
        ->toContain('className="table-fixed"')
        ->toContain('className="max-w-80"');
});

test('company contact table mirrors the useful contact list columns', function () {
    $page = file_get_contents(
        dirname(__DIR__, 2).'/resources/js/pages/companies/show.tsx',
    );

    expect($page)->toBeString()
        ->toContain('className="w-80"')
        ->toContain('<TableHead>Tags</TableHead>')
        ->toContain('<TableHead>Audiences</TableHead>')
        ->toContain('className="w-28"')
        ->toContain('contact.tags.slice(0, 3)')
        ->toContain('contact.audiences')
        ->toContain(".join(', ')")
        ->toContain('className="max-w-48"')
        ->toContain('className="block truncate" title={audienceNames}')
        ->toContain('formatRelativeTime(contact.created_at)')
        ->not->toContain('<TableHead>Email</TableHead>');
});

test('company contacts use the shared filter toolbar without a card wrapper', function () {
    $page = file_get_contents(
        dirname(__DIR__, 2).'/resources/js/pages/companies/show.tsx',
    );

    expect($page)->toBeString()
        ->toContain('<h2 className="text-lg font-semibold">Contacts</h2>')
        ->toContain('<FilterMenu')
        ->toContain('testId="company-contact-filter-button"')
        ->toContain("key: 'audience'")
        ->toContain('<ListSearch')
        ->toContain('<ActiveFilters')
        ->toContain('clearTestId="clear-company-contact-filters"')
        ->not->toContain('<CardTitle>Contacts</CardTitle>');
});

test('the company edit dialog starts from the saved company each time it opens', function () {
    $page = file_get_contents(
        dirname(__DIR__, 2).'/resources/js/pages/companies/show.tsx',
    );

    expect($page)->toBeString()
        ->toContain('setEditGeneration((count) => count + 1)')
        ->toMatch('/<CompanyDialog\s+key=\{editGeneration\}/');
});

test('the company delete action is disabled while the delete runs', function () {
    $page = file_get_contents(
        dirname(__DIR__, 2).'/resources/js/pages/companies/show.tsx',
    );

    expect($page)->toBeString()
        ->toContain('onStart: () => setDeleting(true)')
        ->toContain('onFinish: () => setDeleting(false)')
        ->toMatch('/disabled=\{deleting\}\s*onClick=\{deleteCompany\}/');
});

test('company names on the contacts index and hover card link to the company', function () {
    $indexPage = file_get_contents(
        dirname(__DIR__, 2).'/resources/js/pages/contacts/index.tsx',
    );
    $hoverCard = file_get_contents(
        dirname(__DIR__, 2).'/resources/js/components/contact-hover-card.tsx',
    );

    expect($indexPage)->toBeString()
        ->toContain('href={showCompany.url([teamSlug, contact.company.uuid])}');
    expect($hoverCard)->toBeString()
        ->toMatch('/href=\{showCompany\.url\(\[\s*currentTeam\.slug,\s*contact\.company\.uuid,\s*\]\)\}/');
});
