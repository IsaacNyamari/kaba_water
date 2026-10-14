<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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
    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
        // Let vendors without their own key use the platform key above.
        // Off by default so one vendor's usage can't exhaust the platform quota.
        'platform_fallback' => (bool) env('GEMINI_PLATFORM_FALLBACK', false),
        'models' => array_values(array_filter(array_map(
            'trim',
            explode(',', env('GEMINI_MODELS', 'gemini-flash-lite-latest,gemini-flash-latest'))
        ))),
    ],
];
