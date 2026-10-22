<?php

namespace App\Http\Controllers;

use App\Services\Seo\SeoSettings;
use Illuminate\Http\Response;

/**
 * Arquivo de verificação do IndexNow (/{chave}.txt). Só responde para a chave
 * configurada e com o IndexNow ativo; qualquer outro .txt continua 404.
 */
class IndexNowKeyController extends Controller
{
    public function __invoke(string $key, SeoSettings $settings): Response
    {
        abort_unless($settings->get('indexnow_enabled') && hash_equals((string) $settings->get('indexnow_key'), $key), 404);

        return response($key, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
