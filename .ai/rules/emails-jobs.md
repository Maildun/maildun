---
paths:
  - 'app/{Actions/Emails,Jobs}/**'
---

# Emails Jobs

## Hydrate campaign batches in bounded loader chunks
StartEmailSend queues one PrepareEmailSendChunk instead of materializing recipients in the HTTP request. Each loader snapshots at most 200 subscribed recipients, insert-or-ignores the unique (email_id, subscriber_id) delivery rows, and adds both delivery jobs and the next loader to the same campaign batch. Keep all batched jobs on the configured campaigns queue.
