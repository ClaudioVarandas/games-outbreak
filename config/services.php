<?php

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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],
    'igdb' => [
        'rate_limit_delay_ms' => env('IGDB_RATE_LIMIT_DELAY_MS', 280000), // Default 280ms
        // External sources to sync (IGDB IDs): 1=Steam, 5=GOG, 26=Epic, 36=PlayStation
        'active_external_sources' => array_map('intval', array_filter(
            explode(',', env('IGDB_ACTIVE_EXTERNAL_SOURCES', '1'))
        )),
    ],
    'steamgriddb' => [
        'api_key' => env('STEAMGRIDDB_API_KEY'),
    ],
];
