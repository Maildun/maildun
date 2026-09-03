<?php

test('company actions use text labels without icons', function () {
    $page = file_get_contents(
        dirname(__DIR__, 2).'/resources/js/pages/companies/show.tsx',
    );

    expect($page)->toBeString()
        ->toContain('onClick={() => setEditOpen(true)}')
        ->toContain('onClick={() => setDeleteOpen(true)}')
        ->toMatch('/onClick=\{\(\) => setEditOpen\(true\)\}\s*>\s*Edit\s*<\/Button>/')
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
        ->toContain('onEdit={canManage ? openEdit : undefined}')
        ->toContain('<ContactDialog')
        ->toContain('className="max-w-0"');
});

test('company contact table mirrors the useful contact list columns', function () {
    $page = file_get_contents(
        dirname(__DIR__, 2).'/resources/js/pages/companies/show.tsx',
    );

    expect($page)->toBeString()
        ->toContain('<TableHead>Contact</TableHead>')
        ->toContain('<TableHead>Tags</TableHead>')
        ->toContain('<TableHead>Audiences</TableHead>')
        ->toContain('<TableHead>Created</TableHead>')
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
