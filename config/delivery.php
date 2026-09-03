<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Outbound Send Rate
    |--------------------------------------------------------------------------
    |
    | The maximum number of messages this instance hands to the mail transport
    | per second, across campaigns and transactional email. Every provider
    | enforces a quota of its own and rejects the overflow, so this keeps a
    | large campaign from burning through real recipients on throttling errors.
    |
    | 0 disables the limit, which is the default so existing installs keep
    | their current behavior. A new Amazon SES account starts at 14 messages
    | per second; check your provider's quota before raising it. SMTP relays
    | vary widely, so use whatever your relay documents.
    |
    | "release_after" is how long a job waits before asking for a slot again.
    |
    */

    'rate_limit' => [
        'per_second' => (int) env('MAIL_RATE_LIMIT_PER_SECOND', 0),
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
    | once they have sat still for this many minutes.
    |
    | A delivery already claimed for sending is never re-queued, because the
    | transport may have accepted it. Those are failed instead, so an operator
    | can retry them deliberately from the campaign report.
    |
    */

    'recovery' => [
        'stalled_after_minutes' => (int) env('MAIL_STALLED_AFTER_MINUTES', 15),
        'tracking_redispatch_after_minutes' => (int) env('MAIL_TRACKING_REDISPATCH_AFTER_MINUTES', 5),
    ],

];
