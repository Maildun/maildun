---
paths:
  - 'resources/js/pages/transactional/**,app/Http/Controllers/TransactionalEmailController.php,app/Http/Requests/{StoreTransactionalEmailRequest,UpdateTransactionalEmailRequest}.php'
---

# Transactional Http Controllers Http Requests

## New transactional emails take the team editor; existing ones keep their own
The team email_editor only picks the editor for new transactional emails. Edit, update, duplicate, and the MCP update tool use the email's stored editor, so changing the team setting never drops an existing block design or source. Do not expose or accept a per-email editor switch. The compose picker only offers campaign templates using the active team editor.
