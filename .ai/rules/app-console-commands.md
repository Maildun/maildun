---
paths:
  - '{app/Console/Commands/*Storage*.php,config/filesystems.php,.env.example}'
---

# App Console Commands

## Custom S3 storage uses path-style addressing
When storage:configure selects the custom S3-compatible provider (including MinIO), write AWS_USE_PATH_STYLE_ENDPOINT=true; AWS S3 and R2 keep it false. MinIO virtual-host URLs such as bucket.minio.host may not resolve, while path-style endpoint/bucket URLs do.
