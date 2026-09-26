---
paths:
  - '{app/Console/Commands/ResumeEmailDeliveriesCommand.php,app/Actions/Emails/FinalizeEmailSend.php,routes/console.php}'
---

# Commands Actions Emails

## Stalled sends need a sweeper; never re-queue a claimed delivery
A campaign leans on a Redis batch to move every delivery, and FinalizeEmailSend only runs if that batch survives. A flush, eviction, or queue reset strands the campaign at Sending with no jobs left — and the report refuses to retry it, because retrying is blocked while a campaign is active. `emails:resume` (scheduled every 5 minutes, withoutOverlapping) recovers that.

The split that matters: a delivery still Queued with send_attempted_at null never reached a transport, so it is safely re-dispatched (claim() makes a racing duplicate lose). A delivery already Sending WAS handed over and may have been accepted — those are failed, never re-queued, or recipients get mailed twice. Same reasoning as FinalizeEmailSend.

FinalizeEmailSend::__invoke takes a nullable Batch so the sweeper can close a campaign whose batch record is already gone, which is the exact stranding case.

## Campaign batches live in the database, not Redis
Correction to "Stalled sends need a sweeper": job batches are stored in the job_batches table (config/queue.php batching.database), not Redis. A Redis flush or eviction loses the queued jobs rather than the batch record, which strands a campaign the same way; the claim/re-queue split and emails:resume behaviour described above still apply. routes/console.php also schedules emails:send-scheduled every minute (withoutOverlapping) to start scheduled drafts.
