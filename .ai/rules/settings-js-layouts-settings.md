---
paths:
  - '{app/Http/Controllers/Settings/SystemCheckController.php,resources/js/pages/settings/system-check.tsx,resources/js/layouts/settings/layout.tsx}'
---

# Settings Js Layouts Settings

## System checks are owner-only
The deployment diagnostics page and its storage round-trip action are restricted to the active workspace owner. Keep the sidebar entry hidden for every other role and retain the server-side authorization check.
