---
paths:
  - 'resources/js/pages/subscribe-forms/**,resources/js/components/subscribe-form-view.tsx,app/Http/Controllers/*SubscribeForm*.php,app/Http/Requests/PublicSubscribeRequest.php,app/Actions/Audiences/SubscribeToAudience.php'
---

# Requests Actions Audiences

## Audience attributes are collected by every hosted form
Every audience attribute is shown in the subscribe-form editor preview and hosted form in position order. Public submissions validate each field by its configured type, reject unknown keys, and store non-empty values in subscribers.attribute_values. Keep the public response generic and idempotent.
