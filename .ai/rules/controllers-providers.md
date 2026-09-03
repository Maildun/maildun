---
paths:
  - 'app/Mail/**,app/Http/Controllers/UnsubscribeController.php,app/Providers/AppServiceProvider.php'
---

# Controllers Providers

## Automation mail unsubscribes by subscriber, not delivery
Automation sends write no EmailDelivery row, so their opt-out is keyed to the subscriber: unsubscribe/s/{subscriber}, signed, covered by the existing unsubscribe/* CSRF exemption. Two path segments, so it cannot collide with unsubscribe/{delivery}. The audience comes off the subscriber rather than $delivery->email->audience.

AutomationEmail::oneClickUrl() (POST store) goes in the List-Unsubscribe header; AutomationEmail::unsubscribeUrl() (GET show) is what {{ unsubscribe_url }} resolves to in the body. Do not swap them — a browser following the POST route with GET gets a 405. The merge tag is spread LAST in AdvanceAutomationRun::mergeData so no custom attribute or trigger context can shadow the opt-out link.

The public-unsubscribe limiter must key on whichever of delivery/subscriber the route carries. Keying only on delivery makes every automation opt-out share one empty-string bucket at 10/min, which throttles unrelated recipients out of unsubscribing — the exact failure the per-recipient key exists to prevent.
