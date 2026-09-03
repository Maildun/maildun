---
paths:
  - 'app/{Jobs,Models,Console/Commands}/**'
---

# Jobs Models Console Commands

## Preserve insight aggregates when pruning events
Tracking insight aggregates and per-delivery uniqueness rows are durable reporting state. The event-retention command may delete only processed email_tracking_events after the configured period; it must not delete aggregate or uniqueness rows.
