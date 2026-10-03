---
paths:
  - 'app/{Http/Controllers,Http/Requests,Jobs,Models}/**/*ContactImport*.php,resources/js/components/contact-import-menu.tsx,resources/js/pages/{contacts,audiences}/**'
---

# Contactsaudiences

## Contact imports preserve shared profiles and memberships
CSV contact imports run through ProcessContactImport on the queue. Uploading creates a draft (delimiter, headers, sample rows, row count, suggested column_map); nothing is imported until contacts.imports.start saves the mapping. An audience import reuses an existing Contact and creates only a missing Subscriber membership. Never overwrite a non-empty value on an existing shared Contact or Subscriber: the default merge strategy (skip) leaves existing contacts untouched, and the opt-in fill_blanks strategy only writes empty names and attribute values and adds (never removes) tags. Unsubscribed members stay unsubscribed unless the import explicitly enables resubscribe_unsubscribed, and audience imports require consent confirmation. Progress, counts and row errors live on the dedicated contacts/imports/show page, polled while the import runs; failed imports keep their file so they can retry from file_offset.
