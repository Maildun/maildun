<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Workspace API Rate Limits
    |--------------------------------------------------------------------------
    |
    | Every API key is held to 120 requests a minute. A workspace can issue
    | many keys, so "per_team_per_minute" caps all of its keys together.
    |
    | "per_team_per_day" optionally caps a workspace's API requests across a
    | whole day, for example to bound what a leaked key can send. 0 turns it
    | off, which is the default.
    |
    */

    'rate_limit' => [
        'per_team_per_minute' => (int) env('API_RATE_LIMIT_PER_TEAM_PER_MINUTE', 300),
        'per_team_per_day' => (int) env('API_RATE_LIMIT_PER_TEAM_PER_DAY', 0),
    ],

];
