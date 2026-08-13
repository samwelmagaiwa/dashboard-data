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

    'circuit_breaker' => [
        // Consecutive failures before the circuit opens and stops hitting the API.
        'failure_threshold' => (int) env('CIRCUIT_BREAKER_FAILURE_THRESHOLD', 2),
        // Seconds the circuit stays OPEN before allowing a probe request (HALF_OPEN).
        'open_ttl'          => (int) env('CIRCUIT_BREAKER_OPEN_TTL', 300),
    ],

    'gap_detection' => [
        // Weekdays that are not expected to have dashboard data.
        // Carbon weekday numbering: 0 (Sunday) through 6 (Saturday).
        'non_operational_weekdays' => array_values(array_filter(array_map(
            static fn ($value) => is_numeric($value) ? (int) $value : null,
            explode(',', env('DASHBOARD_NON_OPERATIONAL_WEEKDAYS', '0'))
        ), static fn ($value) => $value !== null && $value >= 0 && $value <= 6)),

        // Optional comma-separated YYYY-MM-DD dates to suppress from gap detection
        // for public holidays, planned shutdowns, migrations, etc.
        'excluded_dates' => array_values(array_filter(array_map(
            static fn ($value) => trim($value),
            explode(',', env('DASHBOARD_EXCLUDED_DATES', ''))
        ))),
    ],
];
