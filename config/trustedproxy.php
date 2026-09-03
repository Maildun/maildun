<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted Proxies
    |--------------------------------------------------------------------------
    |
    | Behind a load balancer or CDN, the client address arrives in the
    | X-Forwarded-For header rather than REMOTE_ADDR. Until the proxy is
    | trusted, Request::ip() reports the proxy itself, which collapses every
    | visitor into a single rate limiter bucket and records the wrong address
    | in subscribers.consent_ip.
    |
    | Set TRUSTED_PROXIES to a comma separated list of proxy addresses, or to
    | "*" when the balancer has no stable address. Leave it empty when the
    | application is served directly, otherwise clients can spoof their own
    | address. Laravel Cloud, Forge, and Vapor are detected automatically.
    |
    */

    'proxies' => env('TRUSTED_PROXIES'),

];
