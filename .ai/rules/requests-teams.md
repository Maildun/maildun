---
paths:
  - 'app/{Models,Services,Http/Controllers/Teams,Http/Requests/Teams}/**/*.php'
---

# Requests Teams

## Keep workspace email activation explicit
Store at most one integration per team+provider, with teams.active_email_integration_id selecting the sender. The one-provider-per-type and first-connection auto-activation parts are superseded below. Deleting the active row clears selection and must never auto-switch. Resolve the active integration fresh and team-scoped at send time; provider-specific tests must use TeamMailer::sendWithIntegration without mutating active state.

## Multiple accounts per email provider
Supersedes the earlier team+provider uniqueness rule: allow multiple integrations with the same EmailProvider. Existing connections are identified by UUID and have a required display name; active_email_integration_id alone chooses the sender. Connections never auto-activate; update/test never switches and deleting active pauses delivery.

## One email connection per workspace (supersedes activation rules)
Supersedes "Keep workspace email activation explicit" and "Multiple accounts per email provider". team_email_integrations is one row per team (enforce_single_email_delivery_per_workspace); Team::emailIntegration() is a HasOne and there is no active_email_integration_id. Still resolve the connection fresh and team-scoped at send time, and provider tests still go through TeamMailer::sendWithIntegration.
