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
        // Asking a property owner's number (POST /api/v1/properties/{id}/contact).
        'contact' => [
            'per_user_per_hour' => (int) env('AWEZ_CONTACT_RATE_LIMIT_PER_HOUR', 30),
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

    /*
    | Sign-in with Google and Apple (ADR 0003): the app gets an ID token natively and the API
    | verifies it against the provider's published keys. Client ids are comma-separated: Google's
    | Android, iOS and web clients; Apple's bundle id (and a services id, if a web sign-in comes).
    |
    | `fake` accepts `fake:<email>` as an ID token, for real runs before the clients exist. The app
    | refuses to boot with it outside local and testing.
    */
    'sign_in' => [
        'fake' => (bool) env('AWEZ_SIGN_IN_FAKE', false),
        'leeway_seconds' => 60,
        // When the keys' response gives no max-age.
        'keys_cache_seconds' => 3600,
        'google' => [
            'client_ids' => array_values(array_filter(explode(',', (string) env('AWEZ_GOOGLE_CLIENT_IDS', '')))),
            'keys_url' => 'https://www.googleapis.com/oauth2/v3/certs',
            'issuers' => ['accounts.google.com', 'https://accounts.google.com'],
        ],
        'apple' => [
            'client_ids' => array_values(array_filter(explode(',', (string) env('AWEZ_APPLE_CLIENT_IDS', '')))),
            'keys_url' => 'https://appleid.apple.com/auth/keys',
            'issuers' => ['https://appleid.apple.com'],
        ],
    ],

    /*
    | Confirming a resident's mobile number (ADR 0003). `log` writes the code to the
    | `phone_verification` log channel and is refused at boot outside local and testing;
    | `whatsapp_inbound` comes with step 4.3b. Each limit allows `max` starts in `minutes`.
    */
    'phone_verification' => [
        'driver' => env('AWEZ_PHONE_VERIFICATION_DRIVER', 'log'),
        'code_length' => 6,
        'expires_minutes' => 10,
        'max_attempts' => 5,
        'rate_limits' => [
            'phone' => [['max' => 3, 'minutes' => 10], ['max' => 10, 'minutes' => 1440]],
            'user' => [['max' => 5, 'minutes' => 60]],
            'ip' => [['max' => 20, 'minutes' => 60]],
        ],
    ],

    /*
    | Photos and documents (spatie/laravel-medialibrary). Every collection takes its disk from here,
    | so moving to Cloudflare R2 is `.env` only: MEDIA_DISK=r2 and MEDIA_PRIVATE_DISK=r2_private.
    | Public files are served by URL; private ones (agreements' documents) only through a signed URL
    | an admin asks for. Limits an admin may tune are in MediaSettings, not here.
    */
    'media' => [
        'disk' => env('MEDIA_DISK', 'public'),
        'private_disk' => env('MEDIA_PRIVATE_DISK', 'local'),
        // The conversions run on their own queue, so a burst of uploads never delays notifications.
        'queue' => 'media',
        // The cleaned original: upright, no metadata, at most this many pixels on its long side.
        'max_long_side' => 2560,
        'jpeg_quality' => 85,
        // Refused before decoding: a small file can declare a huge canvas (a decompression bomb).
        // 120 megapixels lets a 108 MP phone photo through.
        'max_pixels' => 120_000_000,
        // Widths of the WebP conversions, never enlarged.
        'conversions' => [
            'thumb' => 480,
            'large' => 1600,
        ],
        'webp_quality' => 80,
        'pending_upload_hours' => 24,
        'signed_url_minutes' => 5,
    ],

    /*
    | A signed-in request writes `last_seen_at` when the stored one is older than this.
    */
    'accounts' => [
        'last_seen_every_minutes' => 5,
    ],

];
