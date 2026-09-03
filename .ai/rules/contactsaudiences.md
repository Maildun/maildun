---
paths:
  - 'app/{Http/Controllers,Http/Requests,Jobs,Models}/**/*ContactImport*.php,resources/js/components/contact-import-dialog.tsx,resources/js/pages/{contacts,audiences}/**'
---

# Contactsaudiences

## Contact imports preserve shared profiles and memberships
CSV contact imports run through ProcessContactImport on the queue. A workspace import skips an existing team email as a duplicate; an audience import reuses that Contact and creates only a missing Subscriber membership. Never overwrite an existing shared Contact profile during import. Keep per-import progress and duplicate or failed row counts visible through contactImports polling.
