---
paths:
  - 'resources/js/pages/subscribe-forms/**'
---

# Pages Subscribe Forms

## Subscribe form logo saves must spoof PATCH
Logo uploads cannot use form.patch(). PHP does not parse multipart files on PATCH, so the file is dropped. POST to Wayfinder update.form().action (query _method=PATCH) with forceFormData when a File is present, matching team and profile logo forms.
