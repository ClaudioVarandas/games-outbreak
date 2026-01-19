<?php

return [
    /*
    |--------------------------------------------------------------------------
    | News Feature
    |--------------------------------------------------------------------------
    |
    | Master toggle for the news feature.
    | Values:
    |   - true/'true'   : Enabled for everyone (public + admin)
    |   - 'admin'       : Admin preview mode (only admins can access)
    |   - false/'false' : Disabled for everyone (404)
    |
    */
    'news' => env('FEATURE_NEWS', 'admin'),

    /*
    |--------------------------------------------------------------------------
    | News URL Import
    |--------------------------------------------------------------------------
    |
    | Sub-feature toggle for URL import functionality.
    | Only checked if the main news feature is enabled.
    |
    */
    'news_url_import' => env('FEATURE_NEWS_URL_IMPORT', true),
];
