---
paths:
  - 'app/{Actions/Emails,Jobs}/**'
---

# Emails Jobs

## Hydrate campaign batches in bounded loader chunks
StartEmailSend queues one PrepareEmailSendChunk instead of materializing recipients in the HTTP request. Each loader snapshots at most 200 subscribed recipients, insert-or-ignores the unique (email_id, subscriber_id) delivery rows, and adds both delivery jobs and the next loader to the same campaign batch. Keep all batched jobs on the configured campaigns queue. While a send rate is configured the loader also waits for its campaign's backlog to drain before loading the next page (see concerns.md).

A failed loader does not touch the campaign status: the deliveries it already queued must still be claimable, and ResolveCampaignOutcome reports Failed when fewer deliveries exist than recipient_count, which offers the rest through QueueRemainingRecipients.

## Retries re-arm in pages too
RetryEmailDeliveries re-arms only the first 200 retryable deliveries (by id, row-locked) in the request and queues a PrepareEmailRetryChunk in the same batch for the rest; each page re-checks retryableFor, so a page never re-arms a row twice. The last page corrects the run's recipient_count. Its public signature and the flash/status behaviour stay the same.

## Every campaign batch records itself before its jobs run
Start, retry and remaining-recipient batches pass RecordCampaignBatch as the batch's before callback, so emails.batch_id names the new batch before any job is pushed. FinalizeEmailSend ignores a finished batch that is not the campaign's current one (a stopped batch running out its leftovers during a retry); without the before callback a fast batch could finish before batch_id was written and never close its campaign.
