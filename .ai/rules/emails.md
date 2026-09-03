---
paths:
  - 'resources/js/lib/email-builder.ts resources/js/components/email-*.tsx resources/js/pages/emails/**'
---

# Emails

## EmailBuilder.js is vendored stock — do not restyle or rewrite it
`@usewaypoint/email-builder` publishes only `Reader`, `ReaderBlock` and `renderToStaticMarkup`. The drag-and-drop editor is the upstream Vite+MUI sample, vendored in `resources/js/email-builder/` (from `examples/vite-emailbuilder-mui`). Do not rebuild it in shadcn. `email-builder-editor.tsx` is only a ThemeProvider + document/onChange bridge so the inspect drawer stays inside the compose card. Keep MUI, inspect, and HTML/JSON tabs. Do not mount the upstream samples drawer (Waypoint templates / marketing).

Block prop shapes live in `resources/js/types/emails.ts` and mirror the packages' zod schemas — `EmailLayout` takes flat data, every other block takes `{ style, props }`.

`renderToStaticMarkup` imports `react-dom/server`, so NEVER call `renderBuilderHtml()` during a render pass (that nests one renderer inside another). Call it only from event handlers — form submit, or when opening the save-as-template dialog. For live preview, render `<Reader>` as a normal component instead.

The server never renders block documents: the client stores both `design` (the JSON) and the `html` it rendered from it, and `emails.html` is what gets sent. `UpdateEmailRequest` therefore requires `html` in both editor modes.
