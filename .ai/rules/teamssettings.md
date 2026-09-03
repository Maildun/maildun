---
paths:
  - 'resources/js/pages/{teams,settings}/**/*.tsx'
---

# Teamssettings

## Hidden inputs driven by React state need local dirty tracking
Inertia v3 <Form> computes isDirty only from DOM input/change/reset events on the form. A hidden input whose value comes from useState (editor cards, brand-theme selects) changes silently, so isDirty stays false and a Save button gated on it can never be clicked. Compare local state against the server prop and OR it in (const formIsDirty = isDirty || hasXChanges), then feed formIsDirty to both UnsavedChangesGuard and the submit button. See teams/theme.tsx and teams/email.tsx.
