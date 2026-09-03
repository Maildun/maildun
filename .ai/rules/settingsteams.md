---
paths:
  - 'resources/js/pages/{settings,teams}/**/*.tsx'
---

# Settingsteams

## Settings pages do not export breadcrumbs
The shared settings layout intentionally has no breadcrumb header, so settings and team-settings pages must not export layout breadcrumb data. Their page heading is the visible context.
