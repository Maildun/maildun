---
paths:
  - 'resources/js/email-builder/**,resources/js/components/email-builder-editor.tsx,resources/js/lib/email-builder.ts'
---

# Lib

## Keep EmailBuilder.js stock
The block editor is the upstream Vite+MUI sample vendored in resources/js/email-builder/. Do not rebuild it with shadcn. email-builder-editor.tsx only mounts ThemeProvider, syncs the zustand document with the Inertia form, and contains the inspect drawer inside the compose card. Do not mount the samples drawer. HTML is still rendered client-side with renderBuilderHtml() on save.
