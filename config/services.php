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
    'jina' => [
        'api_key' => env('JINA_API_KEY'),
    ],
    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-haiku-4-5-20251001'),
        'version' => '2023-06-01',
    ],
    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
    ],
    'news_ai_provider' => env('NEWS_AI_PROVIDER', 'openai'),

    'telegram' => [
        'enabled' => env('TELEGRAM_BROADCAST_ENABLED', false),
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'chat_id' => env('TELEGRAM_CHAT_ID'),
    ],

    'x' => [
        'enabled' => env('X_BROADCAST_ENABLED', false),
        'api_key' => env('X_API_KEY'),
        'api_secret' => env('X_API_SECRET'),
        'access_token' => env('X_ACCESS_TOKEN'),
        'access_token_secret' => env('X_ACCESS_TOKEN_SECRET'),
    ],

    'youtube' => [
        'api_key' => env('YOUTUBE_API_KEY'),
    ],
];
