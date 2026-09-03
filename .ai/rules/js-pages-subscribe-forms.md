---
paths:
  - 'app/Models/SubscribeForm.php,app/Http/Controllers/SubscribeFormController.php,app/Jobs/ProcessSubscribeFormImage.php,resources/js/pages/subscribe-forms/**'
---

# Js Pages Subscribe Forms

## Subscribe form artwork is queued WebP media
New form artwork is uploaded as a file, held on the local disk while processing, then converted to WebP by ProcessSubscribeFormImage and promoted to image_path on the public disk. The editor polls image_processing; keep legacy image_url as a read-only fallback for existing forms.

## Subscribe form artwork uses shared storage in cloud mode
This supersedes the local-only pending-artwork rule. Pending artwork uses the current default disk and the queued job carries the source disk; converted WebP artwork and logos stay on the logical public disk. URLs are /storage locally and AWS_PUBLIC_URL on S3-compatible storage.
