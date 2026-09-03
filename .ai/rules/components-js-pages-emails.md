---
paths:
  - 'resources/js/components/{preview-width-tabs,email-html-editor,email-builder-editor}.tsx,resources/js/pages/emails/show.tsx'
---

# Components Js Pages Emails

## Preview width uses sliding Desktop/Mobile tabs
Desktop vs mobile email preview is PreviewWidthTabs: sliding TabsList with the labels Desktop and Mobile, no device icons. Reuse it for campaign report preview and the HTML editor preview. The EmailBuilder.js canvas keeps the upstream MUI desktop/mobile toggle — do not replace that with PreviewWidthTabs.
