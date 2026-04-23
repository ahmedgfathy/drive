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

    'active_directory' => [
        'scheme' => env('AD_SCHEME', 'ldap'),
        'host' => env('AD_HOST', ''),
        'port' => (int) env('AD_PORT', 389),
        'base_dn' => env('AD_BASE_DN', ''),
        'bind_username' => env('AD_BIND_USERNAME', ''),
        'bind_password' => env('AD_BIND_PASSWORD', ''),
        'timeout' => (int) env('AD_TIMEOUT', 5),
    ],

];
