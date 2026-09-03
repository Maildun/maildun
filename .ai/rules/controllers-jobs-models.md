---
paths:
  - 'app/{Actions/Emails,Http/Controllers,Jobs,Models}/**'
---

# Controllers Jobs Models

## Capture tracking into the durable event inbox
Public open/click requests must insert an immutable email_tracking_events payload before dispatching ProcessEmailTrackingEvent. Only the idempotent processor updates email_deliveries, email_link_clicks, and their aggregate read models, using occurred_at for first/last timestamps. Queue dispatch failure must leave the event pending; emails:dispatch-tracking-events is the recovery path.
