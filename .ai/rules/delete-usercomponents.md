---
paths:
  - 'resources/js/{pages/settings,pages/teams,components/delete-user,components/settings-panel}.tsx'
---

# Delete Usercomponents

## Settings panels use the flat variant
Settings and team-settings pages intentionally use SettingsPanel variant="flat" with no outer card surface. Keep the card variant as the default for authentication, audience, and media consumers.

## Settings use inset-card panels
Supersedes the former flat treatment: settings and team-settings pages use SettingsPanel variant="inset". SettingsPanel renders a Card for every variant and keeps the variant as a data-variant attribute; Members and Tags render the shared Table directly instead of a SettingsPanel.
