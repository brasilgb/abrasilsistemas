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

    'ab_prospect' => [
        'token' => env('AB_PROSPECT_API_TOKEN'),
    ],

    'n8n' => [
        // Webhook do workflow que envia a mensagem pelo WAHA e registra a LeadActivity no CRM.
        'whatsapp_webhook_url' => env('N8N_WHATSAPP_WEBHOOK_URL'),
        'whatsapp_webhook_timeout' => (int) env('N8N_WHATSAPP_WEBHOOK_TIMEOUT', 20),
        // Header Auth do node Webhook do n8n: nome do header e valor da credential.
        'whatsapp_webhook_header' => env('N8N_WHATSAPP_WEBHOOK_HEADER', 'X-CRM-Token'),
        'whatsapp_webhook_token' => env('N8N_WHATSAPP_WEBHOOK_TOKEN'),
    ],

];
