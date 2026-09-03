<?php

test('the theme defines a soft grey active fill and right-edge indicator tokens', function () {
    $css = file_get_contents(dirname(__DIR__, 2).'/resources/css/app.css');

    expect($css)->toBeString()
        ->toContain('--color-sidebar-active: var(--sidebar-active);')
        ->toContain('--color-sidebar-active-foreground: var(--sidebar-active-foreground);')
        ->toContain('--color-sidebar-indicator: var(--sidebar-indicator);')
        ->toContain('--color-sidebar-indicator-hover: var(--sidebar-indicator-hover);');

    preg_match('/:root\s*\{([^}]+)\}/', $css, $root);
    preg_match('/\.dark\s*\{([^}]+)\}/', $css, $dark);

    // Light: a soft grey fill a full step below the hover accent (0.97), so the
    // ladder reads without a white chip, a hairline edge, or a drop shadow.
    expect($root[1] ?? '')
        ->toContain('--sidebar-active: oklch(0.945 0 0)')
        ->toContain('--sidebar-active-foreground: oklch(0.145 0 0)')
        ->toContain('--sidebar-indicator: oklch(0.145 0 0)')
        ->toContain('--sidebar-indicator-hover: oklch(0.87 0 0)');

    expect($dark[1] ?? '')
        ->toContain('--sidebar-active: oklch(0.32 0 0)')
        ->toContain('--sidebar-active-foreground: oklch(1 0 0)')
        ->toContain('--sidebar-indicator: oklch(1 0 0)')
        ->toContain('--sidebar-indicator-hover: oklch(1 0 0 / 0.3)');

    // The raised-chip ring is gone in both modes.
    expect($css)->not->toContain('--sidebar-active-shadow');
});

test('active sidebar rows use the grey fill, not a white chip or the fainter hover accent', function () {
    $sidebar = file_get_contents(dirname(__DIR__, 2).'/resources/js/components/ui/sidebar.tsx');

    // Both the top-level menu button and the nested sub button.
    expect(substr_count($sidebar, 'data-active:bg-sidebar-active data-active:font-medium data-active:text-sidebar-active-foreground'))
        ->toBe(2);

    expect($sidebar)->toContain('hover:bg-sidebar-accent hover:text-sidebar-accent-foreground')
        ->not->toContain('data-active:bg-sidebar-accent')
        ->not->toContain('--sidebar-active-shadow');
});

test('sidebar rows grow a hover bar out of the sidebar\'s own right border', function () {
    $sidebar = file_get_contents(dirname(__DIR__, 2).'/resources/js/components/ui/sidebar.tsx');

    // The bar hangs off the row by the exact distance out to the panel border,
    // so it lands on the border itself rather than on the row's edge.
    expect($sidebar)->toContain('[--sidebar-row-inset:0.5rem]')
        ->toContain('[--sidebar-row-inset:1.9375rem]');

    // Rounded on the left, flat against the border, grown out of it on hover.
    expect(substr_count($sidebar, 'after:right-[calc(var(--sidebar-row-inset)*-1)] after:w-1 after:origin-right after:scale-x-0 after:rounded-l after:bg-sidebar-indicator-hover'))
        ->toBe(2);

    expect(substr_count($sidebar, 'hover:after:scale-x-100 has-focus-visible:after:scale-x-100 motion-reduce:after:transition-none'))
        ->toBe(2);

    // The active row does not draw its own bar — the sliding pill owns that.
    expect($sidebar)->not->toContain('has-data-active:after');
});

test('one shared pill slides along the border to the active row', function () {
    $sidebar = file_get_contents(dirname(__DIR__, 2).'/resources/js/components/ui/sidebar.tsx');

    expect($sidebar)->toContain('function SidebarActiveIndicator(')
        ->toContain('<SidebarActiveIndicator contentRef={contentRef} />');

    // It must live inside the scroll container, and be positioned against it.
    expect($sidebar)->toContain('"no-scrollbar relative flex min-h-0 flex-1 flex-col gap-2 overflow-auto');

    // Measured in content coordinates so it stays glued to its row while scrolling.
    expect($sidebar)->toContain('activeRect.top - contentRect.top + content.scrollTop');

    // Flush with the border, and it slides rather than jumps.
    expect($sidebar)->toContain('absolute top-0 right-0 w-1 rounded-l bg-sidebar-indicator transition-[transform,height] duration-300 ease-out motion-reduce:transition-none');

    // Both top-level and nested rows can own the pill.
    expect($sidebar)->toContain('[data-sidebar="menu-button"][data-active]:not([data-active="false"])')
        ->toContain('[data-sidebar="menu-sub-button"][data-active]:not([data-active="false"])');
});

test('sidebar chrome rows opt out of the indicator', function () {
    $appSidebar = file_get_contents(dirname(__DIR__, 2).'/resources/js/components/app-sidebar.tsx');
    $navUser = file_get_contents(dirname(__DIR__, 2).'/resources/js/components/nav-user.tsx');

    $navSearch = file_get_contents(dirname(__DIR__, 2).'/resources/js/components/nav-search.tsx');

    // The logo, the account row and the command-palette trigger are chrome,
    // not navigation destinations, so they grow no hover bar.
    expect($appSidebar)->toContain('<SidebarMenuItem className="after:hidden">');
    expect($navUser)->toContain('<SidebarMenuItem className="after:hidden">');
    expect($navSearch)->toContain('<SidebarMenuItem className="after:hidden">');
});
