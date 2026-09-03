---
paths:
  - 'app/Jobs/SendEmailDelivery.php,app/Actions/Emails/**'
---

# Actions Emails

## A delivery is claimed once before the transport
SendEmailDelivery claims the row (status Queued + send_attempted_at null -> Sending + now) immediately before Mail::send. A retry after a worker was killed post-handoff finds nothing to claim and stops, so nobody is mailed twice. A transport exception releases the claim back to Queued so the queue's 3 tries still work. FinalizeEmailSend sweeps anything left in Sending to Failed, and RetryEmailDeliveries clears send_attempted_at to re-arm. Do not send from inside a DB transaction, and do not drop the claim to "simplify" the job.
