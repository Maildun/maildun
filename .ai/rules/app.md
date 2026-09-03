---
paths:
  - resources/js/layouts/app/app-sidebar-layout.tsx
  - 'resources/js/layouts/app/**'
---

# App

## Page padding lives on the sidebar layout
App sidebar pages get padding from app-sidebar-layout (`p-10`), aligned with the header (`px-10`). Do not re-add `p-4 pt-0` on individual pages — it stacks. Settings uses its own padded max-width container.

## App pages use a max-w-7xl container
Sidebar app pages wrap the header and body in one `mx-auto w-full max-w-7xl` container so breadcrumbs and content share the same 80rem (1280px) column. Padding stays on the inner body (`p-10`) and header (`px-10`) — do not re-add page-level padding or a second max-width wrapper. Settings keeps its own narrower max-w-4xl container.

## Let wide editors shrink inside the sidebar layout
Keep `min-w-0` on AppContent, the max-w-7xl wrapper, and the padded body column. Wide intrinsic children such as EmailBuilder.js otherwise size the flex ancestors before their own overflow/container rules run, widening the page and breaking navigation on narrow desktop viewports.

## Fullscreen layout hides the app chrome
AppSidebarLayout accepts `fullscreen`. When true (campaign design via setLayoutProps), it presents an h-dvh overflow-hidden shell and visually hides AppSidebar, breadcrumbs, p-10, and attribution. Default remains the sidebar app chrome.

## Fullscreen layout must preserve page state
AppSidebarLayout must keep AppShell, AppContent, and children mounted when fullscreen changes. Switching between separate return trees remounts the campaign editor and resets its local view and unsaved form state; hide or overlay the app chrome with classes instead.
