---
paths:
  - resources/js/components/app-sidebar.tsx
  - 'resources/js/components/{user-menu-content,team-switcher,app-header}.tsx'
  - 'resources/js/components/{app-sidebar,user-menu-content,team-switcher,app-header}.tsx'
  - resources/js/components/user-menu-content.tsx
  - 'resources/js/components/{subscriber-stats-chart,sliding-underline-list}.tsx'
  - resources/js/components/filter-menu.tsx
  - resources/js/components/active-filters.tsx
  - resources/js/components/subscriber-hover-card.tsx
  - 'resources/js/components/{create-team-modal,team-switcher,nav-user,app-header}.tsx'
  - resources/js/components/appearance-tabs.tsx
  - resources/js/components/getting-started-checklist.tsx
  - resources/js/components/email-builder-editor.css
  - resources/js/components/email-report-layout.tsx
  - resources/js/components/contact-import-dialog.tsx
---

# Components

## Sidebar logo must use SidebarMenuButton
The app logo in SidebarHeader must be wrapped in SidebarMenu > SidebarMenuItem > SidebarMenuButton size="lg" asChild (not a raw padded Link). SidebarMenuButton is what shrinks to a 32px icon tile when collapsible="icon". Hide the wordmark with group-data-[collapsible=icon]:hidden on AppLogo's name.

## Sidebar toggle lives in the sidebar header
The collapse control sits in the sidebar header, to the right of the logo. Do not put SidebarTrigger in the page header on desktop (keep `md:hidden` there so mobile can still open the sheet). When the sidebar is collapsed to icons, show the logo until hover (or keyboard focus) on `group/logo`, then swap it for the expand trigger.

## Team switching lives in the user menu
Switch team from the user dropdown (TeamSwitcher is a DropdownMenuSub inside UserMenuContent), not a header/sidebar control and not a list at settings/teams. Create team stays in that submenu.

## Sidebar has no Repository or Documentation links
Do not add starter-kit Repository or Documentation links to the app sidebar footer or header. The sidebar footer is only NavUser.

## Team switcher shows team logos
TeamSwitcher renders team.logo in an Avatar (uploaded file or DiceBear critters from the backend). Initials stay only as AvatarFallback if the image fails to load. Keep switching in that submenu; do not move team logos to a sidebar/header control.

## User menu logout uses Logout02Icon
The Log out item in UserMenuContent uses Hugeicons Logout02Icon, not Logout01Icon or other logout variants.

## Metric tabs use the sliding rounded-t underline
Subscriber stats charts use SlidingUnderlineList. Active metric is the h-1 rounded-t bg-foreground pill; inactive hover slides up after:bg-border from translate-y-full. Keep border-b off CardHeader — CardHeader with border-b injects pb-(--card-spacing) that p-0 does not override. Do not go back to per-button border-b-2 or after:h-0.5. Do not add this chart pattern back to campaign overview.

## Filter menu trigger uses FilterMailIcon
The shared Filter button (FilterMenu trigger on every filterable list) uses Hugeicons FilterMailIcon, not FilterIcon. Submenu field icons stay per-field.

## Active filter chips have no field icon
ActiveFilters chips are field / is / value / dismiss only. Do not pass a Hugeicons field icon. Keep Cancel01Icon on the chip's clear button.

## Subscriber hover card Edit is label-only
The Edit button in SubscriberHoverCard has no icon. Keep Edit03Icon on table overflow and other edit actions.

## Subscriber emails truncate in the table
SubscriberHoverCard trigger name and email use truncate. The wrapping TableCell is max-w-0 so long addresses do not stretch the row. Hover card body already truncates.

## Dialogs opened from a Base UI menu must live outside the menu
Never put a DialogTrigger inside a DropdownMenuItem. Base UI unmounts menu content on close, so the dialog dies with the menu; keeping the menu open instead (closeOnClick={false}) leaves focus on the menu item while the dialog marks the popup aria-hidden/inert — Chrome logs "Blocked aria-hidden on an element because its descendant retained focus", and DialogTrigger also warns about nativeButton because a menu item renders a div.

Follow the Base UI menu docs ("Open a dialog"): host the Dialog as a sibling of the menu and open it imperatively from the item's onClick. CreateTeamModal does this — it wraps the whole DropdownMenu in NavUser and AppHeader, renders the dialog next to it, and exposes useCreateTeam() through context so TeamSwitcher's "New team" item just calls onClick={openCreateTeam} and lets the menu close normally.

## Appearance uses sliding tabs
The account color-mode selector is a controlled shared Tabs group with TabsList variant="sliding". Keep Light, Dark, and System wired to useAppearance so the shared animated indicator replaces manual active-button styling.

## Getting started card toggles from its full summary
The whole Getting Started summary row is the CollapsibleTrigger, not a standalone arrow button. Opening and closing should animate the measured panel height, and each checklist step should enter with a short stagger; keep motion-safe/motion-reduce handling so reduced-motion users get an immediate reveal.

## Contain the builder below its combined panel width
EmailBuilder.js has a 370px canvas minimum plus a 320px inspector. Keep `.email-builder-js` as an inline-size container constrained to 100%, and below 43rem remove the main stack's inspector margin so the absolutely positioned inspector overlays instead of widening the compose form.

## Keep sidebar navigation grouped by workflow
Keep Dashboard under Overview; Audiences and List Hygiene under Manage audience; and Campaigns, Transactional, Automations, Templates, and Media under Manage campaign. The dynamic campaign list is a separate Recent campaigns group.

## Campaign report tabs use a sliding rounded-t underline
Overview/Recipients/Links/Preview are Inertia links, not Tabs. The active tab uses a single h-1 rounded-t bg-foreground pill that translates on page change (scrollLeft-aware, like sliding TabsList) and fades/slides up on first reveal. Hovering an inactive tab slides a soft gray after:bg-border bar up from below (after:translate-y-full → hover:after:translate-y-0); the nav is overflow-hidden so that rise is clipped at the hairline. Keep before:h-px as the hairline. Do not use rounded-full or border-b-2.

## Import uses a dropdown entry point
Contacts and audience lists use the shared Import dropdown trigger with ArrowDown03Icon. Import CSV is a dropdown item that opens the existing queued-import dialog; keep progress polling and recent import results in that dialog.
