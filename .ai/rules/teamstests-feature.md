---
paths:
  - '{app/Models,app/Services,app/Http/Controllers/Teams,app/Jobs,resources/js/pages/teams,tests/Feature}/**/*.{php,tsx}'
---

# Teamstests Feature

## One delivery connection per workspace
A workspace has exactly one email delivery connection. Test delivery first with explicit From and To addresses; this proves only the provider credentials/transport. Every exact workspace sender must then be verified against that connection id and verification_version. Credential/provider changes increment the version and invalidate sender proofs; name-only changes do not. Workspace sending never falls back to the system mailer.
