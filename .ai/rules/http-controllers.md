---
paths:
  - 'resources/js/pages/teams/tags.tsx resources/js/components/tag-*.tsx app/Http/Controllers/TagController.php'
---

# Http Controllers

## Tag management lives in team settings, not the app shell
Tags are managed at settings/teams/{team}/tags (page `teams/tags`, so SettingsLayout applies via app.tsx's `teams/` prefix match), not under the {current_team} app routes — that keeps a settings visit from silently switching the user's current team via EnsureTeamMembership.

Color is always chosen from Tag::COLORS, passed to the page as a `colors` prop and rendered by TagColorSelect (swatch + friendly name; hex-to-name map lives in tag-color-select.tsx). Validation still accepts any #rrggbb, so TagColorSelect prepends an off-palette color as an extra option when editing legacy tags.

Write access is gated on `permissions.canManageTags` (TeamPermission::ManageAudience via TeamPermissions DTO); members get a read-only table.

The settings sidebar picks the active item by longest matching href because the Tags URL sits under the Teams URL — otherwise both entries highlight.

## Tags table uses square swatches, a row dropdown, and select-all bulk delete
TagColorSwatch is a rounded square (rounded-[2px]), not a circle. Row edit/delete live in a MoreHorizontal dropdown (not two icon buttons). Managers get select-all checkboxes; selected rows bulk-delete through tags.bulk-destroy (register that route before {tag} so it is not captured as a uuid). Members still get a read-only table.
