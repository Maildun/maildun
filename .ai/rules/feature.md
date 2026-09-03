---
paths:
  - '{routes/settings.php,app/Http/Controllers/Teams/TeamEmailIntegrationController.php,tests/Feature/TeamEmailIntegrationTest.php}'
---

# Feature

## Guard email integration UUID routes
Legacy GET /email-provider/{provider} URLs must redirect to the canonical /new/{provider} setup page before any UUID query. Resolve canonical GET connection UUIDs with a team-scoped query after Str::isUuid validation, and constrain update/activate/test/destroy route parameters with whereUuid so PostgreSQL never receives provider slugs as uuid values.
