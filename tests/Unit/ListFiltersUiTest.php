<?php

function listPageSource(string $path): string
{
    return file_get_contents(dirname(__DIR__, 2).'/resources/js/'.$path);
}

test('list filter state goes through the shared useListFilters hook', function () {
    $hook = listPageSource('hooks/use-list-filters.ts');

    expect($hook)->toBeString()
        ->toContain('export function useListFilters')
        ->toContain('useClearFiltersOnEscape(hasActiveFilters, clearAll)')
        ->toContain("searchField = 'q'")
        ->toContain('preserveState: true')
        ->toContain('{ ...filters, ...next }');
});

test('every filterable list uses the shared filter bar', function () {
    $pages = [
        'pages/media/index.tsx',
        'pages/email-templates/index.tsx',
        'pages/emails/index.tsx',
        'pages/transactional/index.tsx',
        'pages/audiences/show.tsx',
    ];

    foreach ($pages as $page) {
        expect(listPageSource($page))
            ->toContain("from '@/components/filter-menu'")
            ->toContain("from '@/components/list-search'")
            ->toContain("from '@/components/active-filters'")
            ->toContain("from '@/hooks/use-list-filters'");
    }

    // Audiences keep their sliding tabs and sort menu, so only the chips are shared.
    expect(listPageSource('pages/audiences/index.tsx'))
        ->toContain("from '@/components/list-search'")
        ->toContain("from '@/components/active-filters'")
        ->toContain("from '@/hooks/use-list-filters'")
        ->toContain('<TabsList variant="sliding">');
});

test('each list names its filter controls for the tests', function () {
    expect(listPageSource('pages/email-templates/index.tsx'))
        ->toContain('testId="template-filter-button"')
        ->toContain("testId: 'template-editor-filter'")
        ->toContain("testId: 'template-type-filter'")
        ->toContain('clearTestId="clear-template-filters"')
        ->toContain('placeholder="Search templates"')
        ->toContain("'No matching templates'")
        ->and(listPageSource('pages/emails/index.tsx'))
        ->toContain('testId="campaign-filter-button"')
        ->toContain('icon: StatusIcon')
        ->toContain("testId: 'campaign-status-filter'")
        ->toContain("testId: 'campaign-audience-filter'")
        ->toContain("testId: 'campaign-editor-filter'")
        ->toContain('clearTestId="clear-campaign-filters"')
        ->toContain('placeholder="Search campaigns"')
        ->toContain("'No matching campaigns'")
        ->and(listPageSource('pages/transactional/index.tsx'))
        ->toContain('testId="transactional-filter-button"')
        ->toContain('icon: StatusIcon')
        ->toContain("testId: 'transactional-status-filter'")
        ->toContain("testId: 'transactional-editor-filter'")
        ->toContain('clearTestId="clear-transactional-filters"')
        ->toContain('placeholder="Search transactional emails"')
        ->toContain("'No matching transactional emails'")
        ->and(listPageSource('pages/audiences/index.tsx'))
        ->toContain('clearTestId="clear-audience-filters"')
        ->toContain('placeholder="Search audiences"')
        ->and(listPageSource('pages/audiences/show.tsx'))
        ->toContain('testId="subscriber-filter-button"')
        ->toContain("testId: 'subscriber-source-filter'")
        ->toContain('clearTestId="clear-subscriber-filters"')
        ->toContain('placeholder="Search name or email"');
});

test('the filter menu counts active fields and the chips clear one at a time', function () {
    $menu = listPageSource('components/filter-menu.tsx');
    $search = listPageSource('components/list-search.tsx');
    $chips = listPageSource('components/active-filters.tsx');

    expect($menu)->toBeString()
        ->toContain('data-test={testId}')
        ->toContain('FilterMailIcon')
        ->toContain('className="size-3.5"')
        ->toContain('const count = visibleFields.filter(fieldIsActive).length')
        ->and($search)->toBeString()
        ->toContain('SEARCH_DEBOUNCE_MS = 400')
        ->toContain('placeholder={placeholder}')
        ->and($chips)->toBeString()
        ->toContain('Cancel01Icon')
        ->not->toContain('icon: IconSvgElement')
        ->not->toContain('icon={icon}')
        ->not->toContain('icon={filter.icon}');
});
