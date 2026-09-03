---
paths:
  - 'resources/js/components/ui/sidebar.tsx,resources/css/app.css'
---

# Ui Css

## Active sidebar rows are a soft grey fill plus a pill on the sidebar's right border
Supersedes the raised-white-chip rule. No white fill, no hairline edge, no drop shadow — `--sidebar-active-shadow` is gone.

Ladder in light mode: rest panel ~0.9925 -> hover `--sidebar-accent` 0.97 -> active `--sidebar-active` 0.945. Dark keeps 0.269 -> 0.32.

The indicator is modelled on the campaign report tabs, rotated onto the sidebar's own `border-r`:
- Active is ONE shared pill (`SidebarActiveIndicator` inside `SidebarContent`) that translates vertically between rows, so navigation slides instead of jumping. It lives in the scroll container (`SidebarContent` is now `relative`) and is measured in content coordinates (`activeRect.top - contentRect.top + content.scrollTop`), so it stays glued while scrolling — same scroll-aware rule as the sliding TabsList. `persistedSidebarIndicator` at module scope keeps it from re-fading on an Inertia layout remount.
- Hover is a per-row `::after` on SidebarMenuItem / SidebarMenuSubItem using `--sidebar-indicator-hover`, grown out of the border with `origin-right scale-x-0 -> scale-x-100`. Do NOT park it with `translate-x-full`: SidebarContent is `overflow-auto` and a transformed box outside it adds scrollable overflow.
- Both reach past the group padding via `--sidebar-row-inset` (0.5rem on menu items, 1.9375rem on sub items = group px-2 + sub mx-3.5 + px-2.5 - translate-x-px). The bar cannot live on the button — it is `overflow-hidden`.

Colours are `--sidebar-indicator` (active) and `--sidebar-indicator-hover`, registered in both the `@theme` and `@theme inline` blocks.

Chrome rows opt out with `after:hidden` on their SidebarMenuItem: the logo (app-sidebar.tsx), the account row (nav-user.tsx) and the command-palette Search row (nav-search.tsx) are not navigation destinations.

shadcn add/apply overwrites ui/sidebar.tsx and drops all of it — re-merge after any regenerate. Covered by tests/Unit/SidebarActiveStateUiTest.php.
