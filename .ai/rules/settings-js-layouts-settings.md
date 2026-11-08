---
paths:
  - '{app/Http/Controllers/Settings/SystemCheckController.php,resources/js/pages/settings/system-check.tsx,resources/js/layouts/settings/layout.tsx}'
---

# Settings Js Layouts Settings

## System checks are operator-only
Supersedes "System checks are owner-only". The deployment diagnostics, the storage round-trip, the update refresh, and the shared `appUpdate` prop describe the whole installation, so they belong to the instance operator, never to a workspace owner: every account owns its personal workspace. Every one of them authorizes through the `viewSystemCheck` gate, which delegates to `viewHorizon` (HorizonServiceProvider): a verified account whose address is in HORIZON_ALLOWED_EMAILS. Do not reintroduce a team-scoped check. The settings sidebar shows System Check only when `appUpdate` is non-null, because HandleInertiaRequests shares it through the same gate; keep the server-side authorization as well.
