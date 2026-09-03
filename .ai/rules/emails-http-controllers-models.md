---
paths:
  - 'app/{Actions/Emails,Http/Controllers,Models}/**'
---

# Emails Http Controllers Models

## Tracking aggregates are transactional read models
email_deliveries and email_link_clicks remain authoritative. Open/click handlers must update email_tracking_aggregates and email_link_tracking_aggregates in the same transaction, and campaign/link report totals must read those aggregates. Repair drift with emails:rebuild-tracking; its revision check prevents reconciliation from overwriting a concurrent tracking event.
