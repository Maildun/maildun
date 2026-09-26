---
paths:
  - 'app/{Models,Services,Http/Controllers/Teams,Http/Requests/Teams}/**/*.php,database/{migrations,factories}/**/*.php,resources/js/{pages/teams,components}/**/*email-provider*.tsx,routes/settings.php,tests/**/*EmailIntegration*.php'
---

# Teamscomponents

## Email provider connections are independently addressable
A team may store multiple encrypted email connections for the same EmailProvider. Address existing connections by their UUID and require a human-readable connection name; provider enum values only select the adapter when creating. Keep exactly one active_email_integration_id: connections never auto-select, never switch during update/test, and clear the active id (pausing delivery) when that connection is deleted.

## One email connection per workspace (supersedes independently addressable connections)
Supersedes "Email provider connections are independently addressable". A workspace has at most one TeamEmailIntegration (database-enforced by enforce_single_email_delivery_per_workspace); Team::emailIntegration() is a HasOne. Connections are still routed by UUID, but there is no active_email_integration_id and no switching between connections.
