---
paths:
  - 'app/Models/Media.php,app/Actions/Media/**,app/Http/Controllers/Media*.php,resources/js/pages/media/**,resources/js/components/media-*.tsx'
---

# Media Js Components

## Media has its own categories and tags
Media taxonomy is team-scoped and separate from subscriber Tag. Each file has one optional MediaCategory (belongsTo) and many MediaTag (belongsToMany via media_media_tag). Categories and tags are created inline from the media detail sheet with firstOrCreate on name; the library filters with q, category (uuid or uncategorized), and tag (uuid). Do not reuse the audience tags table or TagController.
