<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Local IP Geolocation
    |--------------------------------------------------------------------------
    |
    | Maildun reads DB-IP Lite MMDB files locally while capturing an event. The
    | raw address is immediately discarded after deriving geography, ASN, and
    | its keyed hash. Missing databases simply leave enrichment fields empty.
    |
    */

    'geolocation' => [
        'enabled' => env('MAIL_TRACKING_GEOLOCATION_ENABLED', true),
        'city_database' => env('MAIL_TRACKING_CITY_DATABASE')
            ?: storage_path('app/private/geoip/dbip-city-lite.mmdb'),
        'asn_database' => env('MAIL_TRACKING_ASN_DATABASE')
            ?: storage_path('app/private/geoip/dbip-asn-lite.mmdb'),
        'source' => 'db-ip-lite',
    ],

    /*
    |--------------------------------------------------------------------------
    | Event Payload Retention
    |--------------------------------------------------------------------------
    |
    | Processed tracking events support auditing and aggregate repair, but they
    | contain a keyed IP hash, user agent, and derived location. The scheduled
    | pruner removes those payloads after this period while keeping privacy-safe
    | campaign insight aggregates. Set days to 0 to disable automatic pruning.
    |
    */

    'retention' => [
        'days' => (int) env('MAIL_TRACKING_EVENT_RETENTION_DAYS', 90),
        'batch_size' => (int) env('MAIL_TRACKING_PRUNE_BATCH_SIZE', 1000),
    ],

];
