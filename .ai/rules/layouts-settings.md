---
paths:
  - resources/js/layouts/settings/layout.tsx
---

# Layouts Settings

## Settings layout owns mobile nav and breadcrumbs
SettingsLayout accepts breadcrumbs from page `.layout` props and renders AppSidebarHeader-style chrome: SidebarTrigger is md:hidden in the content header, with Breadcrumbs beside it. Do not drop breadcrumbs, and do not omit the mobile trigger — without it the settings sidebar Sheet cannot be opened on phones.

## Settings omit desktop breadcrumbs
Settings pages intentionally have no desktop breadcrumb header. Keep only a mobile-only SidebarTrigger so the settings navigation remains reachable on small screens.

## Settings content width
The shared settings content container uses max-w-4xl. Keep the standard app-style sidebar and outer spacing, but do not widen the settings forms beyond this limit.

## Settings navigation tone
Settings sidebar navigation rows use text-sidebar-foreground/70 to match the main app sidebar. The shared SidebarMenuButton active state supplies the stronger accent foreground and active background.
