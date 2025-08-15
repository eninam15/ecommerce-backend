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

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

     'frontend' => [
        'url' => env('FRONTEND_URL', 'http://localhost:3000'),
        'order_tracking_url' => env('FRONTEND_URL', 'http://localhost:3000') . '/orders',
        'payment_url' => env('FRONTEND_URL', 'http://localhost:3000') . '/payments',
    ],

    'notifications' => [
        'order_confirmation_delay' => env('ORDER_CONFIRMATION_DELAY', 0), // minutos
        'order_reminder_delay' => env('ORDER_REMINDER_DELAY', 24 * 60), // minutos (24 horas)
        'payment_timeout' => env('PAYMENT_TIMEOUT', 30), // minutos
    ],

    'ppe' => [
        'base_url' => env('PPE_BASE_URL', 'https://api.ppe.bo'),
        'token' => env('PPE_TOKEN'),
        'merchant_id' => env('PPE_MERCHANT_ID'),
    ],

];
