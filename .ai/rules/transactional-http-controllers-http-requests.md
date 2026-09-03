---
paths:
  - 'resources/js/pages/transactional/**,app/Http/Controllers/TransactionalEmailController.php,app/Http/Requests/{StoreTransactionalEmailRequest,UpdateTransactionalEmailRequest}.php'
---

# Transactional Http Controllers Http Requests

## Transactional editor is team scoped
The team email_editor determines every transactional compose page and persisted save. Do not expose or accept a per-email editor switch. The compose picker only offers campaign templates using the active team editor.
