---
paths:
  - 'app/Http/Controllers/EmailController.php,app/Http/Controllers/TransactionalEmailController.php,app/Models/EmailTemplate.php'
---

# Controllers Models

## Starting from a template copies subject and preheader
When composing a campaign or transactional email from a template, copy html/design plus subject and preheader. A null or blank template subject falls back to the draft name. Blank starters keep a null subject so the author's name wins.
