<?php

declare(strict_types=1);

return [

    /*
    | Times are stored and computed in UTC (app.timezone). Presentation code converts to this zone,
    | and places' opening hours are wall-clock times in it.
    */
    'display_timezone' => 'Africa/Cairo',

    /*
    | The languages the API answers in. The default is app.locale (Arabic).
    */
    'locales' => ['ar', 'en'],

    /*
    | Requests per minute for the `api` rate limiter. Guests are counted per IP address, and
    | many residents share one: Egyptian mobile networks put customers behind shared addresses.
    */
    'rate_limits' => [
        'api' => [
            'per_user_per_minute' => (int) env('AWEZ_API_RATE_LIMIT_PER_USER', 60),
            'per_ip_per_minute' => (int) env('AWEZ_API_RATE_LIMIT_PER_IP', 300),
        ],
    ],

    /*
    | pg_trgm similarity from which a normalized search term matches a normalized text.
    | Below pg_trgm's own default (0.3): two swapped letters in a short word score 0.27, and
    | unrelated words score under 0.1 (DECISIONS.md, tests/Feature/Support/TrigramThresholdTest.php).
    */
    'search' => [
        'similarity_threshold' => 0.25,
    ],

];
