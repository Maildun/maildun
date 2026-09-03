---
paths:
  - '{config/delivery.php,config/horizon.php,app/Jobs/Send*Delivery.php,app/Concerns/ThrottlesEmailDelivery.php}'
---

# Concerns

## Delivery queues and send rate are operator-configurable
config/delivery.php owns the self-hosted knobs: MAIL_RATE_LIMIT_PER_SECOND (0 = off, SES starts at 14/s), MAIL_CAMPAIGN_QUEUE, MAIL_TRANSACTIONAL_QUEUE, MAIL_STALLED_AFTER_MINUTES. Campaign and transactional sends run on separate queues with their own Horizon supervisors so a large campaign cannot starve transactional mail, automations, or media. Bare `queue:work` must name the queues or nothing sends.

ThrottlesEmailDelivery adds the rate limiter only when per_second > 0, so the default install is byte-for-byte unchanged. When it IS on, the trait switches retryUntil() from null to a 6-hour window: a throttled job is released each time it misses a slot and every release consumes an attempt, so $tries would fail recipients nothing is wrong with. $maxExceptions = 3 is what still stops three genuine transport failures — do not "simplify" that back to $tries alone.

RateLimitedWithRedis is used only when cache.default is redis; the plain RateLimited keeps database/file cache installs working.
