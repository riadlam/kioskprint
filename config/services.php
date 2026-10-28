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

    'sofizpay' => [
        'enabled' => env('SOFIZPAY_ENABLED', true),
        'sandbox' => env('SOFIZPAY_SANDBOX', false),
        'base_url' => env('SOFIZPAY_BASE_URL', 'https://sofizpay.com'),
        'merchant_account' => env('SOFIZPAY_MERCHANT_ACCOUNT'),
        'timeout' => (int) env('SOFIZPAY_TIMEOUT', 30),
        'redirect' => env('SOFIZPAY_REDIRECT', 'no'),
        'keep_return_url' => env('SOFIZPAY_KEEP_RETURN_URL', 'True'),
        'min_amount_dzd' => (float) env('SOFIZPAY_MIN_AMOUNT_DZD', 75),
    ],

];
