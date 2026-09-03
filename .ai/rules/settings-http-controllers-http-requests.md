---
paths:
  - 'resources/js/pages/audiences/settings/sender.tsx,app/Http/Controllers/{AudienceController,AudienceSettingsController}.php,app/Http/Requests/SaveAudienceRequest.php'
---

# Settings Http Controllers Http Requests

## Audience sender uses verified workspace selector
Audience sender settings are a select, never free-text name/address/reply-to inputs. Offer Workspace default plus verified TeamSender records only. Workspace default clears audience overrides; selecting a sender copies its name, email, and reply-to, and pending or cross-workspace sender UUIDs must be rejected.
