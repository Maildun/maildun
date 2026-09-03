---
paths:
  - 'app/Mail/**,app/Actions/Emails/**,app/Http/Controllers/UnsubscribeController.php,resources/js/pages/unsubscribe/**'
---

# Unsubscribe

## Campaigns carry a one-click unsubscribe
Every CampaignEmail sets List-Unsubscribe (signed public.unsubscribe.store URL) and List-Unsubscribe-Post: List-Unsubscribe=One-Click, on every transport, not just SES. Gmail and Yahoo require both on bulk mail. RFC 8058 providers POST that URL with no session, so unsubscribe/* is CSRF exempt and the route is signed instead. BuildTrackedEmailHtml also injects a visible footer link before </body>, unless the author placed {{ unsubscribe_url }} themselves. Opting out reuses SubscriberController::unsubscribe's transition (Unsubscribed + unsubscribed_at + SubscriberLifecycleOccurred) and is idempotent; a configured audience unsubscribed_url takes over the confirmation page.
