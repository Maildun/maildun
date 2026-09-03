---
paths:
  - 'resources/js/components/settings-page-header.tsx,resources/js/pages/{settings,teams}/**/*.tsx'
---

# Pagessettingsteams

## Settings page headers are title-only
SettingsPageHeader renders the h1 (and an optional description) with no leading icon — the `icon` prop was removed, so do not re-add one or place a HugeiconsIcon beside the title. Account pages (profile, security, appearance) also drop the eyebrow label; icons stay in the settings sidebar navigation only.
