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

    /*
    |--------------------------------------------------------------------------
    | Signer (sidecar de firma digital criptográfica PAdES)
    |--------------------------------------------------------------------------
    |
    | Sidecar HTTP interno (FastAPI, ver signer/app.py) alcanzable SOLO desde
    | la red interna de Docker (servicio "signer", sin puerto publicado al
    | host). Expone GET /health, POST /sign, POST /verify. Consumido por
    | App\Services\DocumentSigningService.
    |
    */

    'signer' => [
        'base_url' => env('SIGNER_BASE_URL', 'http://signer:8000'),
        'timeout' => env('SIGNER_TIMEOUT', 120),
        'connect_timeout' => env('SIGNER_CONNECT_TIMEOUT', 10),
    ],

];
