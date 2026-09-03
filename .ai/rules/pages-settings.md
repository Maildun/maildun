---
paths:
  - 'resources/js/pages/settings/**/*.tsx'
  - resources/js/pages/settings/profile.tsx
---

# Pages Settings

## Settings forms toast, guard dirty state, and share Save changes
Profile, team edit, email, theme, and security forms use Inertia Form isDirty: UnsavedChangesGuard (router before + beforeunload, skip non-GET and prefetch), toast.add({ type: 'success', title: 'Changes saved.' }) with no id, focusFirstInvalidField on error, and a Save changes button (w-full sm:w-auto) inside the last SettingsPanel. Panel stacks use gap-6. Do not save silently or leave dirty navigation unguarded.

## Profile avatar and email status controls
Keep profile photo upload on the clickable Avatar label with a hover and keyboard-focus camera overlay; preserve the existing multipart input and preview reset. Email verification is shown with Badge: success for verified and secondary for unverified, without a status icon.
