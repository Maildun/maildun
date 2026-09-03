---
paths:
  - 'resources/js/pages/audiences/**'
  - resources/js/pages/audiences/show.tsx
---

# Audiences

## Audience index lists rows in a table
The audiences index uses a plain table (no Card wrapper): Audience (DiceBear shape-grid logo + name), Status, Subscribed, Segments, Forms, Created, Actions. Keep search/filter/sort above it. Do not wrap the table in a Card or go back to a card grid.

## Audience logos are DiceBear shape-grid
Each audience gets a stable logo from `https://api.dicebear.com/10.x/shape-grid/svg?seed={uuid}` via Audience::avatar, same pattern as Subscriber Micah avatars. Show it in the index table next to the name as a rounded-md square (not a circle) so the clip matches the hairline border. Do not switch audiences to Micah or critters.

## Subscriber lists are plain tables
The audience show subscribers tab and segment matching-subscribers list use a plain Table (Micah avatar + name/email, status badge, source). Do not wrap those rows in a Card. Keep the interactive subscriber stats chart (metric tabs + 28-day line vs previous period) above the audience subscriber toolbar; put status tabs, search, source filter, and Add subscriber in the toolbar above the table.

## Segment and form lists are plain tables
On audience show, the Segments and Subscribe forms tabs use a plain Table with a toolbar (short description + Add button) above the rows. Do not wrap those lists in a Card. Segment row overflow: View, Edit Rule (opens the rules dialog), Delete (confirm; subscribers stay). Form row overflow: Edit/View. Not a lone arrow button. Members only get View.

## Audience settings are dedicated pages
Audience settings use dedicated pages (not tabs or a dialog): General at audiences.edit, plus Sender, Email notification, Attributes, Landing pages, and Danger zone under audiences.settings.*. Wrap them in AudienceSettingsLayout (left nav + SettingsPanel, still inside AppLayout). Authorize with the update policy so Members cannot open the pages.

## Audience settings save lives in the card
Audience settings Save changes sits inside the SettingsPanel card (border-t footer), not below it. Overview row icons use className size-4. Every Input and Textarea on these pages has a non-empty placeholder (sender fields fall back to an example when the team sender is blank).

## Subscriber status belongs in the Filter menu
On audience show, All, Subscribed, and Unsubscribed are Status choices inside the shared FilterMenu alongside Source. Do not restore the separate sliding status tabs. This supersedes the older subscriber status-tabs guidance in audiences.md and js-pages.md.
