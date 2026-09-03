---
paths:
  - 'resources/js/components/getting-started-checklist.tsx,resources/js/components/app-sidebar.tsx,app/Actions/Teams/BuildOnboardingChecklist.php,app/Http/Middleware/HandleInertiaRequests.php'
---

# Http Middleware

## Getting started checklist is derived, never stored
The sidebar checklist (GettingStartedChecklist, rendered in SidebarFooter above NavUser) is driven by the shared `onboarding` prop built in BuildOnboardingChecklist: one query with withExists over audiences/subscribers/emails(sent_at)/automations, plus the team's email_from_address. There is no onboarding table or "dismissed" flag — a step ticks the moment the underlying record exists, and the whole card disappears once completed === total. The backend only returns step keys + booleans; titles, copy, icons, and routes live in STEP_COPY on the frontend. Team::subscribers() (hasManyThrough Audience) exists for that exists-check.

## Current onboarding supersedes the legacy sender flag
This supersedes the earlier email_from_address-only checklist rule. Track delivery and sender separately: delivery requires a verified TeamEmailIntegration, while sender requires the selected default address to remain authorized for that integration and verification version.
