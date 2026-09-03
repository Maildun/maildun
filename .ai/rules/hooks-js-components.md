---
paths:
  - 'resources/js/pages/**/*.tsx,resources/js/hooks/use-list-filters.ts,resources/js/components/{filter-menu,list-search,active-filters}.tsx'
---

# Hooks Js Components

## List filters use the shared filter bar and useListFilters
Every filterable list (media, templates, campaigns, transactional, audiences, subscribers) uses the same pieces: FilterMenu (Filter button + count + one submenu per field), ListSearch (debounced, on the right), and ActiveFilters chips with Clear Filters + Kbd Esc. Query-string state goes through useListFilters({ url, filters, empty, searchField }) — do not hand-roll router.get + debounce + Esc per page.

`empty` is both the reset payload and the definition of "active", and visit() spreads the current filters first, so a field left out of `empty` (the audiences sort, the audience tab on other pages) survives Clear Filters. Bump-key the ListSearch with the returned `searchKey` so clearing resets its uncontrolled input. Audiences keep their sliding tabs + sort menu and only take the search and chips.
