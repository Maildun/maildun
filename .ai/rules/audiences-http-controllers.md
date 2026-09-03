---
paths:
  - 'resources/js/pages/audiences/**,app/Http/Controllers/*Audience*.php,resources/js/layouts/audiences/**'
---

# Audiences Http Controllers

## Audience settings are dedicated pages
Audience settings use dedicated routes under the audience, not a dialog and not query-string tabs. General stays at `audiences.edit` (`audiences/edit`); Sender, Email notification, Attributes, Landing pages, and Danger zone live at `audiences.settings.*` (`audiences/settings/*`). Wrap each page in AudienceSettingsLayout (left nav + SettingsPanel content, still inside AppLayout). Authorize with the update policy so Members cannot open any settings page. Old `?tab=attributes` / `?tab=landing-pages` URLs redirect to the dedicated pages.

## Audience index is a searchable table
The audiences index is a table (not a card grid). Toolbar: sliding filter tabs (all/active/empty/segments/forms), debounced search, and a sort dropdown (newest/oldest/subscribers). Each row has a status badge and an overflow menu: Manage (show), Quick edit (name/description dialog on the index), Delete (name confirmation). Members only get Manage. Do not restore a lone Manage button.
