<?php

return [
    /*
    |--------------------------------------------------------------------------
    | SEO (ABRASIL-SEO-02)
    |--------------------------------------------------------------------------
    |
    | As preferências editoriais ficam no painel (SEO → Configurações). Aqui só
    | ficam o que depende do ambiente e os limites de segurança da auditoria.
    |
    */

    // Homologação: true bloqueia a indexação (robots.txt Disallow: / e noindex em todas as páginas).
    'block_indexing' => (bool) env('SEO_BLOCK_INDEXING', false),

    'sitemap_limit' => 45000,

    'audit' => [
        // Endereço que a auditoria acessa (ex.: http://nginx interno). Vazio = domínio canônico.
        // Só esse host (e o canônico) são aceitos: a auditoria nunca segue para outros hosts.
        'base_url' => env('SEO_AUDIT_BASE_URL'),
        'concurrency' => 4,
        'timeout' => 10,
        'max_redirects' => 5,
        'max_bytes' => 2_000_000,
        'max_pages_limit' => 1000,
    ],

    'google' => [
        'client_id' => env('GOOGLE_SEARCH_CONSOLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_SEARCH_CONSOLE_CLIENT_SECRET'),
    ],
];
