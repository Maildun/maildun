---
paths:
  - routes/web.php
---

# Routes

## Register static subscriber bulk routes before the resource
PATCH/DELETE `audiences/{audience}/subscribers/bulk-*` must be declared before `Route::resource('audiences.subscribers')`. Otherwise `{subscriber}` captures `bulk-unsubscribe` / `bulk-destroy` and the request 404s.

## Audience settings routes are dedicated GETs
Audience settings pages are dedicated GET routes: audiences.edit for General, and audiences.settings.sender|notifications|attributes|landing-pages|danger for the rest. Keep them in the scoped {current_team} group with the audience resource. Do not put settings back behind a ?tab= query on a single edit page.
