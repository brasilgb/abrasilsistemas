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

    'mercadopago' => [
        'token' => env('MP_ACCESS_TOKEN'),
        'webhook_token' => env('MP_WEBHOOK_TOKEN'),
    ],

    // Token das rotas legadas /api/prospects/* e /api/whatsapp/messages/* (extensão e n8n), até
    // essas integrações passarem para o VetorLeads.
    'ab_prospect' => [
        'token' => env('AB_PROSPECT_API_TOKEN'),
    ],

    'waha' => [
        // Usado só pelo Laravel (server-side) para gerenciar a conexão da sessão; nunca vai ao frontend.
        'base_url' => env('WAHA_BASE_URL'),
        'api_key' => env('WAHA_API_KEY'),
        'timeout' => (int) env('WAHA_TIMEOUT', 15),
        // Chave HMAC do webhook do atendimento (config "hmac.key" do webhook na sessão do WAHA).
        // Vazia = endpoint /api/whatsapp/waha/webhook desligado.
        'webhook_hmac_key' => env('WAHA_WEBHOOK_HMAC_KEY'),
    ],

];
