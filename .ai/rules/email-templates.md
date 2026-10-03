---
paths:
  - resources/js/pages/email-templates/edit.tsx
  - resources/js/pages/email-templates/index.tsx
---

# Email Templates

## Template compose is a setup hub
email-templates/edit.tsx mirrors campaign compose: one useForm behind a CampaignSetupRow hub (Details, Subject, Design) with no breadcrumbs. Details and Subject open w-96 dialogs that PATCH from their own Save; Design is a fullscreen canvas (setLayoutProps fullscreen) with an h-14 navbar (back, rename trigger, Save). Keep SECTIONS in sync so the red dot and onError reveal the owning section. Templates have no audience, sender, test, or send.

## Template gallery is a three-column preview grid
The templates index is an artifact-style gallery: page title plus New template, then a grid (two columns from `sm`, three from `lg`). Each item is a muted rounded frame (`px-4 pt-4 overflow-hidden`) with an inset bordered email panel that runs off the bottom (`h-[calc(100%+1.5rem)] rounded-t-xl border border-b-0`). The miniature is top-aligned so the email clips at the bottom of the frame. Then the template name and a relative time (or the team-editor mismatch note). Scale the 600px email in `ScaledEmailFrame` with `origin-top` and `justify-center` so the miniature sits in the middle of the thumbnail. Clickable cards only fade in a shadow on hover (`transition-shadow` + `group-hover:shadow-md`, 300ms). No lift or zoom. Disabled when `prefers-reduced-motion`. Clicking a matching-editor card Uses it (name-only compose dialog). Edit, Duplicate, and Delete live in the overflow menu; starters have no Edit. When Use is opened from a specific template, the compose dialog only asks for a campaign name and does not re-show the template picker.
