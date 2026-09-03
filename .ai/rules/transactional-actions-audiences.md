---
paths:
  - '{routes/api.php,app/Http/Middleware/AuthenticateTeamApiKey.php,app/Http/Controllers/Api/**,app/Actions/Transactional/**,app/Actions/Audiences/**}'
---

# Transactional Actions Audiences

## Keep the public API team-scoped and idempotent
Versioned /api/v1 routes authenticate hashed maildun_live bearer tokens after a pre-auth throttle, then apply the per-key throttle. Resolve templates and audiences through the authenticated team before validating or mutating. Transactional sends honor Idempotency-Key within a team; subscriber subscribe/unsubscribe transitions are idempotent and unsubscribe responses stay opaque.
