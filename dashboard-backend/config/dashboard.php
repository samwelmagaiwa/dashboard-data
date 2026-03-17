<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Dashboard Configuration
    |--------------------------------------------------------------------------
    */

    'cache_version' => 7, // Increment this to force-clear stale cache data

    'sync' => [
        'username' => env('DASHBOARD_API_USERNAME'),
        'password' => env('DASHBOARD_API_PASSWORD'),
        'base_url' => env('DASHBOARD_API_BASE_URL'),
    ],
];
