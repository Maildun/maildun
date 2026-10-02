---
paths:
  - 'resources/js/components/email-builder-editor.tsx,resources/js/lib/email-builder.ts'
---

# Lib

## The email editor is the @maildun/email-builder package
`email-builder-editor.tsx` mounts the package's `EmailEditor` and `lib/email-builder.ts` is the only adapter. Do not vendor the editor back into resources/js or rebuild it with shadcn. HTML is still rendered client-side with renderBuilderHtml() on save.
