---
paths:
  - 'resources/js/pages/emails/**,app/Http/Controllers/EmailController.php,app/Http/Requests/{StoreEmailRequest,UpdateEmailRequest}.php'
  - 'resources/js/pages/emails/edit.tsx,app/Http/Controllers/EmailController.php,app/Http/Requests/UpdateEmailRequest.php'
---

# Http Controllers Http Requests

## Email editor is team scoped
The team email_editor determines every compose page and persisted email save. Do not expose or accept a per-email editor switch. The compose picker only offers templates using the active team editor; retain incompatible templates in the library but do not let them start a draft.

## Campaign sender uses audience default or verified override
Campaign sender editing is a select, never free-text fields. The default option clears campaign sender fields so Email resolves through the selected audience and then workspace. A campaign may override with any verified workspace TeamSender that also passes the active provider domain rule. Preserve an unmatched legacy sender until the user deliberately changes it.
