---
paths:
  - 'resources/js/{pages/settings,pages/teams,components/delete-user,components/settings-panel}.tsx'
---

# Delete Usercomponents

## Settings panels use the flat variant
Settings and team-settings pages intentionally use SettingsPanel variant="flat" with no outer card surface. Keep the card variant as the default for authentication, audience, and media consumers.

## Settings use inset-card panels
Supersedes the former flat treatment: settings and team-settings pages use SettingsPanel variant="inset". It has a muted padded outer shell and a subtle bordered inner surface; keep the default card variant for other consumers.
