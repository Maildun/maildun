---
paths:
  - 'app/{Actions/Emails,Http/Controllers,Jobs,Mail,Services}/**'
---

# Controllers Jobs Mail Services

## Keep campaign feedback SES attempt-scoped
V1 supports Amazon SES and SMTP only. SMTP is handoff-only; Maildun owns campaign open/click tracking. Every claimed campaign send must append an immutable EmailDeliveryAttempt and use one resolved transport snapshot. SES sends its configuration set plus attempt_uuid and persists the SNS topic hash; signed feedback must correlate to that attempt/topic. Feedback from an older attempt never changes the delivery's current outcome, although a permanent bounce or complaint still suppresses the subscriber.
