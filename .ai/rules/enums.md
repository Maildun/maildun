---
paths:
  - '{config/filesystems.php,.env.example,app/Console/Commands/*Storage*.php,app/Enums/StorageBackend.php}'
---

# Enums

## Keep AWS S3 and Cloudflare R2 environment settings separate
FILESYSTEM_DISK remains local or s3 for backend compatibility. When it is s3, OBJECT_STORAGE_PROVIDER selects aws, r2, or custom. AWS and custom use AWS_*; Cloudflare R2 exclusively uses R2_*. Never expose credentials or raw probe exceptions in installation diagnostics.
