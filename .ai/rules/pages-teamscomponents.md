---
paths:
  - 'app/{Models,Services,Http/Controllers/Teams,Http/Requests/Teams}/**/*.php,resources/js/{pages/teams,components}/**/*email-provider*.tsx,tests/**/*EmailIntegration*.php'
---

# Pages Teamscomponents

## Authorize the exact workspace sender before activation
Provider credentials and sender authorization are separate. A successful provider test authorizes only the normalized current workspace From address; connect/update/test never auto-selects a provider, and activation requires that authorization. Credential or From-address changes revoke authorization, clear the active integration when affected, and normal TeamMailer delivery must fail closed until the connection is retested and explicitly selected.
