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
        // How many hours after an event's start_at the live re-sync keeps polling IGDB for new games.
        'event_sync_window_hours' => (int) env('IGDB_EVENT_SYNC_WINDOW_HOURS', 3),
        // During an event sync, refresh an already-known game from IGDB when its data is older
        // than this many hours (keeps list game data fresh without a separate refresh command).
        'event_game_refresh_hours' => (int) env('IGDB_EVENT_GAME_REFRESH_HOURS', 24),
        // Per-game trailer matching window relative to the event start_at: how many hours before
        // start a reveal may post (lead) and how many hours after start to still consider it.
        'event_trailer_lead_hours' => (int) env('IGDB_EVENT_TRAILER_LEAD_HOURS', 1),
        'event_trailer_window_hours' => (int) env('IGDB_EVENT_TRAILER_WINDOW_HOURS', 24),
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
        'model' => env('OPENAI_MODEL', 'gpt-5.4-nano'),
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
        // How many pages (50 uploads each) of a channel's recent videos to walk when matching trailers.
        'channel_max_pages' => (int) env('YOUTUBE_CHANNEL_MAX_PAGES', 6),
    ],
];
