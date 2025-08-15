<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Mailer
    |--------------------------------------------------------------------------
    |
    | This option controls the default mailer that is used to send all email
    | messages unless another mailer is explicitly specified when sending
    | the message. All additional mailers that may be configured are defined
    | within the "mailers" array. Examples of each type of mailer may be
    | found in the Laravel documentation.
    |
    */

    'default' => env('MAIL_MAILER', 'log'),

    /*
    |--------------------------------------------------------------------------
    | Mailer Configurations
    |--------------------------------------------------------------------------
    |
    | Here you may configure all of the mailers used by your application plus
    | their respective settings. Several examples have been configured for
    | you and you are free to add your own as the application requires.
    |
    | Laravel supports a variety of mail "transport" drivers that can be used
    | when delivering an email. You may specify which one you're using for
    | your mailers below. You may also add additional mailers if required.
    |
    | Supported: "smtp", "sendmail", "mailgun", "ses", "ses-v2",
    |            "postmark", "resend", "log", "array", "failover", "roundrobin"
    |
    */

    'mailers' => [

        'smtp' => [
            'transport' => 'smtp',
            'url' => env('MAIL_URL'),
            'host' => env('MAIL_HOST', '127.0.0.1'),
            'port' => env('MAIL_PORT', 2525),
            'encryption' => env('MAIL_ENCRYPTION', 'tls'),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            'timeout' => null,
            'local_domain' => env('MAIL_EHLO_DOMAIN', parse_url(env('APP_URL', 'http://localhost'), PHP_URL_HOST)),
        ],

        'ses' => [
            'transport' => 'ses',
        ],

        'postmark' => [
            'transport' => 'postmark',
            // 'message_stream_id' => env('POSTMARK_MESSAGE_STREAM_ID'),
            // 'client' => [
            //     'timeout' => 5,
            // ],
        ],

        'resend' => [
            'transport' => 'resend',
        ],

        'sendmail' => [
            'transport' => 'sendmail',
            'path' => env('MAIL_SENDMAIL_PATH', '/usr/sbin/sendmail -bs -i'),
        ],

        'log' => [
            'transport' => 'log',
            'channel' => env('MAIL_LOG_CHANNEL'),
        ],

        'array' => [
            'transport' => 'array',
        ],

        'failover' => [
            'transport' => 'failover',
            'mailers' => [
                'smtp',
                'log',
            ],
        ],

        'roundrobin' => [
            'transport' => 'roundrobin',
            'mailers' => [
                'ses',
                'postmark',
            ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Global "From" Address
    |--------------------------------------------------------------------------
    |
    | You may wish for all emails sent by your application to be sent from
    | the same address. Here you may specify a name and address that is
    | used globally for all emails that are sent by your application.
    |
    */

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
        'name' => env('MAIL_FROM_NAME', env('APP_NAME')),
    ],

    // ===== CONFIGURACIONES ADICIONALES PARA ECOMMERCE =====

    /*
    |--------------------------------------------------------------------------
    | Mail Control Settings
    |--------------------------------------------------------------------------
    |
    | These settings allow you to control when emails are actually sent
    | vs logged/stored for testing purposes.
    |
    */

    'enabled' => env('MAIL_ENABLED', true),
    'send_in_development' => env('MAIL_SEND_IN_DEVELOPMENT', false),
    'log_all_emails' => env('MAIL_LOG_ALL', true),
    'test_recipient' => env('MAIL_TEST_RECIPIENT', null),

    /*
    |--------------------------------------------------------------------------
    | Email Templates Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for email templates and branding
    |
    */

    'templates' => [
        'order_confirmation' => [
            'subject_prefix' => env('MAIL_ORDER_SUBJECT_PREFIX', ''),
            'enabled' => env('MAIL_ORDER_CONFIRMATION_ENABLED', true),
        ],
        'order_status_update' => [
            'enabled' => env('MAIL_ORDER_STATUS_ENABLED', true),
        ],
        'payment_confirmation' => [
            'enabled' => env('MAIL_PAYMENT_CONFIRMATION_ENABLED', true),
        ],
        'welcome_coupon' => [
            'enabled' => env('MAIL_WELCOME_COUPON_ENABLED', true),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration for Emails
    |--------------------------------------------------------------------------
    |
    | Email queue settings to prevent blocking the application
    |
    */

    'queue' => [
        'connection' => env('MAIL_QUEUE_CONNECTION', 'database'),
        'queue' => env('MAIL_QUEUE_NAME', 'notifications'),
        'delay' => env('MAIL_QUEUE_DELAY', 0), // seconds
    ],

];