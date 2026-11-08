<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Outbound Send Rate
    |--------------------------------------------------------------------------
    |
    | The maximum number of messages this instance hands to the mail transport
    | per second, across campaign, transactional and automation email. Every
    | provider enforces a quota of its own and rejects the overflow, so this
    | keeps a large campaign from burning through real recipients on
    | throttling errors.
    |
    | 0 disables the limit, which is the default so existing installs keep
    | their current behavior. A new Amazon SES account starts at 14 messages
    | per second; check your provider's quota before raising it. SMTP relays
    | vary widely, so use whatever your relay documents.
    |
    | "transactional_share" is the percentage of that rate reserved for
    | transactional mail, so a large campaign or automation cannot starve
    | receipts and password resets. It is rounded down but never below one
    | message per second; campaigns and automations share the rest, so the
    | two together never exceed the configured rate. Transactional mail is
    | capped at its share, so raise it if your API sends arrive in bursts.
    | A rate of 1, or a share of 0, leaves every send sharing one limit.
    |
    | "release_after" is how long a job waits before asking for a slot again.
    |
    | While a limit is on, a campaign also loads its recipients a few minutes
    | of sending ahead rather than all at once, so every queued delivery gets
    | its turn well inside the time a throttled job may wait for a slot.
    |
    */

    'rate_limit' => [
        'per_second' => (int) env('MAIL_RATE_LIMIT_PER_SECOND', 0),
        'transactional_share' => (int) env('MAIL_RATE_LIMIT_TRANSACTIONAL_SHARE', 20),
        'release_after' => (int) env('MAIL_RATE_LIMIT_RELEASE_AFTER', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Delivery Queues
    |--------------------------------------------------------------------------
    |
    | Tracking, campaign, and transactional work use dedicated queues so a
    | large campaign cannot delay click redirects, tracking projections,
    | transactional mail, automations, media processing, or segment syncing.
    | Scale them independently in config/horizon.php, or point them all back at
    | "default" to run a single pool.
    |
    */

    'queues' => [
        'tracking' => env('MAIL_TRACKING_QUEUE', 'tracking'),
        'campaigns' => env('MAIL_CAMPAIGN_QUEUE', 'campaigns'),
        'transactional' => env('MAIL_TRANSACTIONAL_QUEUE', 'transactional'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Stalled Delivery Recovery
    |--------------------------------------------------------------------------
    |
    | Queued sends lean on the queue to keep them moving. Redis is not durable,
    | so a flush, eviction, or queue reset on deploy can strand a delivery with
    | no job left to run it. "emails:resume" sweeps those back onto the queue
    | once they have sat still for this many minutes and nothing is left on
    | their queue that could still run them. With a queue shared with other
    | work (for example "default"), recovery waits until that queue drains.
    |
    | A delivery already claimed for sending is never re-queued, because the
    | transport may have accepted it. Those are failed instead, so an operator
    | can retry them deliberately from the campaign report.
    |
    | Tracking events and SES feedback are stored before they are processed.
    | One still unprocessed this many minutes after its job was queued is
    | queued again by emails:dispatch-tracking-events and
    | emails:dispatch-provider-events.
    |
    */

    'recovery' => [
        'stalled_after_minutes' => (int) env('MAIL_STALLED_AFTER_MINUTES', 15),
        'tracking_redispatch_after_minutes' => (int) env('MAIL_TRACKING_REDISPATCH_AFTER_MINUTES', 5),
    ],

];
