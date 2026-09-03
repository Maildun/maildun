---
paths:
  - 'app/Models/Media.php,app/Actions/Media/**,app/Jobs/ProcessMediaImage.php,app/Http/Controllers/Media*.php,resources/js/pages/media/**'
---

# Media

## Media library is team-scoped public files
Team media lives under media/{team_uuid}/ on the public disk (pending uploads on the local disk). In-app url is root-relative /storage/... so thumbnails load on the current host. Copy-link uses the current request host as an absolute URL for pasting into emails — do not use APP_URL. JPG/PNG → WebP is the team convert_uploads_to_webp setting and MUST run on the queue (ProcessMediaImage); GIF/WebP stay as-is and existing files are not batch-converted. Owners/admins have media:manage; members may view and copy links only.

## Media storage follows the configured backend
This supersedes the local-only pending-media rule. Ready media stays on the logical public disk; pending WebP sources use the current default disk and ProcessMediaImage carries that source disk. Local public URLs remain root-relative, while S3/R2 public URLs are absolute AWS_PUBLIC_URL values.
