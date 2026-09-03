<?php

return [
    'current' => env('APP_VERSION', '0.1.0-beta'),

    'update_check' => [
        'enabled' => (bool) env('APP_UPDATE_CHECK_ENABLED', true),
        'manifest_url' => env(
            'APP_UPDATE_MANIFEST_URL',
            'https://raw.githubusercontent.com/abduns/maildun/main/release-manifest.json',
        ),
    ],
];
