<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key'    => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel'              => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // Application token for Pushover reminders; each user supplies their own
    // recipient key in settings. Reminders fan out to every user with a key.
    'pushover' => [
        'token' => env('PUSHOVER_TOKEN'),
    ],

    'gbif' => [
        'base_url' => env('GBIF_BASE_URL', 'https://api.gbif.org/v1'),

        // Bulk backbone export the species seed is built from (species:refresh-seed).
        'backbone_url' => env('GBIF_BACKBONE_URL', 'https://hosted-datasets.gbif.org/datasets/backbone/current/backbone.zip'),

        // GBIF can reach the operator here rather than silently blocking the IP.
        'user_agent' => env('GBIF_USER_AGENT', 'Foliotrak/1.0 (+https://github.com/justincdotme/foliotrak)'),
        'timeout'    => (int) env('GBIF_TIMEOUT', 5),

        // Minimum /species/match confidence to accept a fuzzy result (0-100).
        'match_min_confidence' => (int) env('GBIF_MATCH_MIN_CONFIDENCE', 80),

        // A cached species older than this is refreshed from GBIF on its next hit.
        'cache_ttl_days' => (int) env('GBIF_CACHE_TTL_DAYS', 90),

        // Outbound calls per window, host-keyed, sized far under any plausible GBIF
        // threshold. The cache and the SPA debounce mean real volume is tiny.
        'throttle' => [
            'max_attempts'  => (int) env('GBIF_THROTTLE_MAX_ATTEMPTS', 30),
            'decay_seconds' => (int) env('GBIF_THROTTLE_DECAY_SECONDS', 60),
        ],

        // After a failure the breaker opens for base seconds, doubling per
        // consecutive failure up to the cap, so an outage stops the calls.
        'breaker' => [
            'base_cooldown_seconds' => (int) env('GBIF_BREAKER_BASE_COOLDOWN_SECONDS', 30),
            'max_cooldown_seconds'  => (int) env('GBIF_BREAKER_MAX_COOLDOWN_SECONDS', 600),
        ],
    ],

];
