---
paths:
  - 'resources/js/pages/teams/**/*.tsx'
  - resources/js/pages/teams/theme.tsx
  - resources/js/pages/teams/edit.tsx
  - resources/js/pages/teams/members.tsx
  - resources/js/pages/teams/tags.tsx
---

# Pages Teams

## Team settings forms follow the same save pattern as account settings
Team edit, email, and brand-theme forms match account settings: UnsavedChangesGuard, toast.add Changes saved. (no id), focusFirstInvalidField on error, Save changes inside the last SettingsPanel with w-full sm:w-auto, stack gap-6. Brand theme Preview stays outside the Form. Delete-account copy in delete-user.tsx must describe handover via ReleaseUserTeams, not wiping every team.

## Controlled theme selects need local dirty tracking
The brand-theme page uses controlled Base UI Select components plus hidden inputs. Inertia Form isDirty listens for DOM input/change events, so selector state changes do not make the declarative form dirty by themselves. Keep local comparison against team.brandTheme for the save button and UnsavedChangesGuard.

## Workspace logo uses the profile upload pattern
Keep the workspace logo picker on the clickable logo with a small camera overlay on hover and keyboard focus. Preserve the existing multipart upload, preview reset, member/tag links, and permission-gated workspace actions.

## Workspace logo overlay stays circular
The editable workspace logo is circular. Keep its clickable wrapper, Avatar, fallback, focus ring, and hover camera overlay rounded-full so the interactive layer never becomes a square.

## Pending invitation cancellation is a text action
In Pending invitations, use the small secondary Cancel button with no icon. Keep it wired to the existing cancellation-confirmation dialog rather than restoring an icon-only X action.

## Tags table follows the Members table density
Use the same inset p-3 sm:p-4 table wrapper, h-12 px-5 headers, px-5 py-4 cells, and a compact overflow action column as the Members settings table. Keep the square color swatch next to the tag name, but display only Tag and Subscribers columns; do not add standalone Color or Created columns.

## Sender list is a table, not pills
Workspace senders render in the same table as Members and Tags: inset p-3 sm:p-4 wrapper, h-12 px-5 headers, px-5 py-4 cells, h-20 rows, compact right-aligned overflow action column. Columns are Sender (name over email, Default badge inline), Reply-to (hidden sm:table-cell), and Status (Badge success=Verified / orange=Pending). Do not go back to the rounded-full SenderTag pills.

The row MoreHorizontal dropdown owns Edit, Resend verification (unverified only), Use as default (verified and not default), and a destructive Remove that opens a confirmation dialog. The Edit dialog is a pure name/reply-to form — keep those actions out of it. Empty state uses the Empty component and moves the Add sender button into EmptyContent, matching tags.tsx.
