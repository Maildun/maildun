---
paths:
  - 'config/filesystems.php,app/Enums/StorageBackend.php,app/Services/StorageBackendMigrator.php,app/Console/Commands/SyncStorageBackendCommand.php'
---

# Console Commands

## Both storage backends stay configured; storage:sync moves files between them
config/filesystems.php always defines four concrete disks — local/s3 (private) and local_public/s3_public (public) — plus `public`, which aliases whichever public backend FILESYSTEM_DISK selects. Keeping the inactive side addressable is what lets `php artisan storage:sync --to=local|s3` read one backend and write the other.

Never resolve an upload disk with Storage::getDefaultDriver(); use StorageBackend::current()->privateDisk(). The enum falls back to Local for any unrecognized FILESYSTEM_DISK, so config and code agree instead of splitting private-to-cloud/public-to-local.

Object keys are identical on both backends, so switching needs no path rewrites — public URLs are derived from the active disk at read time. Only rows naming a concrete private disk (email_attachments.disk, media.disk while upload_path is set) get repointed. subscribe_forms has no disk column, so drain the queue before switching or in-flight artwork conversions strand.</note>
</invoke>
