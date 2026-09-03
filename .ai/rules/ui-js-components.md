---
paths:
  - 'resources/css/app.css,resources/js/components/ui/sidebar.tsx,resources/js/components/app-header.tsx'
---

# Ui Js Components

## Sidebar surfaces carry the grainy noise overlay
Every sidebar surface (desktop `sidebar-inner`, the `collapsible="none"` panel, the mobile Sheet in ui/sidebar.tsx, and the header layout's nav drawer in app-header.tsx) uses the `grainy` utility from app.css on top of its existing background color — the color is unchanged, only texture is added.

`grainy` is an feTurbulence SVG data URI painted by a `::before` at `z-index:-1` with `isolation: isolate`, so it sits above the element's own background and below its content. Strength comes from `--grain-opacity` (0.18 light / 0.28 dark). Static elements must also carry `relative`, or the overlay resolves against the nearest positioned ancestor; already-positioned ones (the fixed SheetContent) must not, so the utility deliberately sets no `position`.

shadcn add/apply overwrites ui/sidebar.tsx and drops these classes — re-apply after any regenerate. Covered by tests/Unit/GrainySidebarUiTest.php.
