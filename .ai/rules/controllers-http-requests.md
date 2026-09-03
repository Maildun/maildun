---
paths:
  - 'app/Models/SubscribeForm.php,app/Http/Controllers/*SubscribeForm*.php,app/Http/Requests/SaveSubscribeFormRequest.php'
  - 'app/Models/TransactionalEmail.php,app/Http/Controllers/TransactionalEmailController.php,app/Http/Requests/*Transactional*'
---

# Controllers Http Requests

## Subscribe forms store a layout style
Subscribe forms persist style (card/split/minimal/cover), image_side (left/right, used by split), and optional image_url. Defaults are card + right. Public show must pass these to subscribe-forms/public so the hosted page matches the editor preview.

## Subscribe form logos are uploaded files
Subscribe forms store logo_path plus logo_shape (default/square) and logo_size (small/medium/large). The public URL is the logo accessor, stored on the public disk under subscribe-form-logos/. Upload/replace/remove follows the team-logo pattern: capture the old path inside the DB transaction, save, then delete the old file only after commit. Saving without a file must not clear the existing logo; remove_logo does.

## Transactional slug is the API identifier and freezes after publish
transactional_emails.slug is unique per team (including soft-deleted rows) and is the identifier a later API will call. Auto-generate from the name on create. It may be edited while published_at is null, then frozen after the first publish even if the email is later unpublished.
