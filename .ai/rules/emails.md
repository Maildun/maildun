---
paths:
  - 'resources/js/lib/email-builder.ts resources/js/components/email-*.tsx resources/js/pages/emails/**'
---

# Emails

## The block editor is the @maildun/email-builder package
The drag-and-drop editor and the renderer come from `@maildun/email-builder` (npm, published from Maildun/email-builder). Do not vendor or fork it into the app and do not rebuild it in shadcn; fix the editor upstream. `components/email-builder-editor.tsx` only mounts the package's `EmailEditor` and bridges its document and onChange to the Inertia form, and `lib/email-builder.ts` is the single adapter between the app and the package (render, convert, merge tags).

Designs saved by earlier versions are EmailBuilder.js documents. `toBuilderDocument()` converts them on open (`@maildun/email-builder/compat`) and the next save stores the package's format. The block shapes belong to the package's schema; the app types them loosely in `resources/js/types/emails.ts`.

`renderBuilderHtml()` is pure TypeScript with no React or DOM, so it is safe to call during render.

The server never renders block documents: the client stores both `design` (the JSON) and the `html` it rendered from it, and `emails.html` is what gets sent. `UpdateEmailRequest` therefore requires `html` in both editor modes.
