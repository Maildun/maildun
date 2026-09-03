---
paths:
  - 'resources/js/components/ui/sidebar.tsx,resources/js/components/nav-search.tsx'
---

# Components Ui Js Components

## Keep the secondary SidebarMenuButton variant
`sidebarMenuButtonVariants` has a local `secondary` variant (bg-secondary + text-secondary-foreground, with color-mix hover/active) on top of shadcn's `default` and `outline`. The sidebar Search button in nav-search.tsx uses it — do not swap that row for the shared `Button`, which loses the collapsed-to-icon sizing and tooltip.

shadcn add/apply overwrites ui/sidebar.tsx and drops the variant; re-merge after any regenerate. Covered by tests/Unit/SidebarIconsTest.php.
