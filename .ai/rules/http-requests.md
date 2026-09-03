---
paths:
  - app/Http/Requests/SaveSubscriberRequest.php
---

# Http Requests

## consent_confirmed is create-only; never validate it on update
SaveSubscriberRequest must only register the `consent_confirmed` rule when `$this->isMethod('post')`. Do not use `['sometimes', 'accepted']` for the update branch: the edit dialog's useForm state (SubscriberDialog in resources/js/pages/audiences/show.tsx) always includes `consent_confirmed: false` in its payload, so `sometimes` sees the key present, runs `accepted`, and fails on `false`. The update then silently no-ops — the consent Field that renders that error is only mounted when adding, so the dialog stays open with no visible message. Consent columns are written once at create (and re-captured by ResubscribeSubscriberRequest); update never touches them, so the field is ignored on update by design.
