---
paths:
  - '{config/filesystems.php,app/{Actions/Media,Jobs,Http/Controllers,Models}/**}'
---

# Media Jobs Http Controllers Models

## Storage switches by role without provider-specific upload code
FILESYSTEM_DISK=local keeps private files local and the logical public disk at /storage. FILESYSTEM_DISK=s3 stores private attachments and queued conversion sources in AWS_BUCKET while the logical public disk uses AWS_PUBLIC_BUCKET and AWS_PUBLIC_URL. Queue jobs must carry the actual source disk, public asset records keep disk=public, and S3-compatible public writes must not force object ACLs so AWS bucket policies, CDNs, and Cloudflare R2 custom domains work.
