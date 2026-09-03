---
paths:
  - resources/js/pages/emails/edit.tsx
  - resources/js/pages/emails/preview-and-send.tsx
---

# Pages Emails

## Draft compose is a setup hub
emails/edit is a checklist hub, not seven tabs. One useForm still owns the full campaign. Small sections (name, sender, recipients, subject, additional settings) open the default w-96 DialogContent and PATCH from their own Save button. Design is an in-page canvas with back to the hub, EmailBuilderEditor fill, and its own navbar Save. SECTIONS maps every validated field so onError opens the owning dialog or the design view. Do not bring Tabs or SettingsPanel back onto the draft page.

## One form still owns every draft field
The hub is several dialogs and a design view, but it is still a single useForm. Each section Save submits the complete draft, marks the current values as saved, and closes only after success. SECTIONS must assign every validated field to its owning row so the red dot and onError navigation reveal failures. A field missing from that map silently becomes an invisible, unexplained failed save.

## Campaign design is a fullscreen canvas
The Design step calls setLayoutProps({ fullscreen: true }) so AppSidebarLayout drops the sidebar, breadcrumbs, padding, and attribution. The canvas is an h-dvh shell with an h-14 navbar (back, name, personalization, Save), matching automations/edit. Hub view sets fullscreen false. Keep one useForm; do not split Design onto a second route.

## Draft hub shows a design thumbnail
When Design is complete, the hub row drops the helper summary and renders a scaled live preview (Reader for builder/source, iframe for HTML). Do not call renderBuilderHtml during that preview. Incomplete Design keeps the Start designing helper copy.

## Campaign draft sections save independently
The hub has no global Save. Name, sender, recipients, subject, and additional settings each PATCH the shared useForm from their own Save button and close only on success; Design keeps its navbar Save. Section DialogContent uses the default w-96 width, and Save as template stays only in Campaign actions.

## Preview canvas owns vertical scroll
emails/preview-and-send keeps a viewport-height overflow-auto canvas. Size the sandboxed iframe to the email document height (allow-same-origin, no scripts), scale a matching spacer, and use pointer-events-none so wheel/touch reach the canvas at every zoom. Do not use a fixed-height overflow-hidden iframe; that swallows scrolling.
