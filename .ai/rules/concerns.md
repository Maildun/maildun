---
paths:
  - '{config/delivery.php,config/horizon.php,app/Jobs/Send*Delivery.php,app/Concerns/ThrottlesEmailDelivery.php}'
---

# Concerns

## Delivery queues and send rate are operator-configurable
config/delivery.php owns the self-hosted knobs: MAIL_RATE_LIMIT_PER_SECOND (0 = off, SES starts at 14/s), MAIL_RATE_LIMIT_TRANSACTIONAL_SHARE (default 20%), MAIL_CAMPAIGN_QUEUE, MAIL_TRANSACTIONAL_QUEUE, MAIL_STALLED_AFTER_MINUTES. Campaign and transactional sends run on separate queues with their own Horizon supervisors so a large campaign cannot starve transactional mail, automations, or media. Bare `queue:work` must name the queues or nothing sends.

ThrottlesEmailDelivery adds the rate limiter only when per_second > 0, so the default install sends unthrottled. When it IS on, the trait switches retryUntil() from null to a 6-hour window: a throttled job is released each time it misses a slot and every release consumes an attempt, so $tries would fail recipients nothing is wrong with. $maxExceptions = 3 is what still stops three genuine transport failures — do not "simplify" that back to $tries alone.

RateLimitedWithRedis is used only when cache.default is redis; the plain RateLimited keeps database/file cache installs working.

## The send rate is split into lanes that sum to the quota
Separate queues do not separate the rate: every send waits on a limiter. App\Enums\EmailSendLane splits per_second into a reserved transactional share (floor of the percentage, at least 1/s, never all of it) and the bulk rest; each lane is its own named limiter registered in AppServiceProvider, and the two always sum to per_second. Transactional sends override sendLane(); campaigns and automations stay on Bulk — automation mail is list-driven marketing whose volume scales with enrollments, so putting it in the transactional lane would recreate the starvation the split prevents. A rate of 1, or a share of 0, puts both lanes on the bulk limiter.

## Drop dead campaign jobs before they take a slot
middleware() is middlewareBeforeSendSlot() followed by the throttle, in that order. SendEmailDelivery puts SkipIfBatchCancelled and a "still claimable" check (Queued, never attempted, campaign active) there, so the leftover jobs of a stopped campaign finish without spending the shared rate. The real exactly-once guard is still claim() inside handle(); the pre-check only saves slots.

## Campaign loaders pace themselves while throttled
A delivery's retryUntil window starts when its job is queued, so a loader that queued a whole audience at once would fail every recipient past rate × 6h. PrepareEmailSendChunk and PrepareEmailRetryChunk (PacesCampaignLoading) release themselves for 30s while the campaign already has 300 seconds of the bulk rate queued and unclaimed. Rows queued longer than the stall window do not count, so lost jobs cannot hold the loader forever. A released loader stays pending in the batch, so FinalizeEmailSend cannot run while it paces; the loader's own retryUntil (6h per page) and $maxExceptions = 3 bound it. With no rate limit nothing paces.
