---
paths:
  - 'app/Actions/Transactional/**,app/Http/Controllers/TransactionalEmailController.php,resources/js/pages/transactional/**'
---

# Transactional

## Transactional merge tags are {{ key }}
Subject, preheader, and HTML use {{ first_name }} (optional spaces; keys [a-zA-Z_][a-zA-Z0-9_]*). RenderTransactionalContent HTML-escapes values in markup and leaves unknown tags intact. On save, merge newly detected keys into variables while keeping declared examples. Do not invent a second placeholder syntax.
