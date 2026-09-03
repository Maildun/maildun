---
paths:
  - 'routes/settings.php,resources/js/{routes,actions}/**'
---

# Routesactions

## Workspace settings use workspace URLs
Public workspace settings use the singular /settings/workspace URI while internal route names stay teams.* and the Team domain naming stays unchanged. Keep the unnamed GET /settings/teams/{path?} permanent redirect for old links. Regenerate Wayfinder after changing these URIs.
