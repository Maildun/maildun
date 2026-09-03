---
paths:
  - 'app/Jobs/ProcessMediaImage.php,app/Jobs/ProcessSubscribeFormImage.php,app/Models/SubscribeForm.php,app/Services/StorageBackendMigrator.php'
---

# Models Services

## Pending uploads record their disk; conversion jobs follow the row, not their captured value
Every in-flight upload records the private disk holding its source: media.disk (while upload_path is set) and subscribe_forms.image_upload_disk. storage:sync repoints both, so a backend switch no longer strands artwork mid-conversion.

ProcessMediaImage and ProcessSubscribeFormImage must resolve their source disk as `$row->disk ?? $this->sourceDisk` — the row wins. The constructor value is only a fallback for jobs enqueued before the row was repointed. Applies in handle() and failed(); the early-return path (row missing or path mismatch) still uses the captured disk, since the row cannot be trusted there.

Clear image_upload_disk alongside image_upload_path on promote, failure, and remove_image. Delete a superseded pending upload from its own recorded disk, not the current default — it may predate a switch.

storage:sync copies rather than moves precisely so in-flight jobs keep working; do not delete source files until the queue drains.</note>
</invoke>
