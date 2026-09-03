---
paths:
  - 'config/filesystems.php,app/Models/SubscribeForm.php'
---

# Config Models

## Public disk URLs are root-relative
The public disk url is `/storage`, not APP_URL + /storage. Subscribe form logos (and team logos/avatars) must load on the current host (e.g. https://maildun.test). Absolute APP_URL values like http://localhost:8000 produce broken image links.

## Public asset URLs follow the storage backend
This supersedes the unconditional root-relative URL rule. The local public disk keeps /storage URLs on the current host; FILESYSTEM_DISK=s3 makes the logical public disk use AWS_PUBLIC_BUCKET and the absolute AWS_PUBLIC_URL for AWS S3, a CDN, or an R2 custom domain.
