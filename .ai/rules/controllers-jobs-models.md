---
paths:
  - 'app/{Actions/Emails,Http/Controllers,Jobs,Models}/**'
---

# Controllers Jobs Models

## Capture tracking into the durable event inbox
Public open/click requests must insert an immutable email_tracking_events payload before dispatching ProcessEmailTrackingEvent. Only the idempotent processor updates email_deliveries, email_link_clicks, and their aggregate read models, using occurred_at for first/last timestamps. Queue dispatch failure must leave the event pending; emails:dispatch-tracking-events is the recovery path.

## SES feedback uses the same durable inbox
SNS stops redelivering within minutes, so AwsSesWebhookController must store the verified notification (ProcessSesEvent::record, an email_provider_events row with processed_at null, de-duplicated by the SNS MessageId) before dispatching ProcessEmailProviderEvent, and return 2xx once it is stored. ProcessSesEvent::process locks the row and fills its delivery references and processed_at in the same transaction as every feedback change, so a failure rolls back and leaves it pending, and a duplicate run does nothing. emails:dispatch-provider-events is the recovery path. ProcessSesEvent::handle is record + process in one call.
