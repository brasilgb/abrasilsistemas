<?php

namespace App\Services\Seo;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * IndexNow (Bing, Yandex e outros; o Google não participa): avisa que uma URL foi
 * publicada ou alterada. Desligado por padrão; quando ligado, a chave é servida em
 * /{chave}.txt pelo IndexNowKeyController. Só envia URLs do domínio canônico.
 */
class IndexNow
{
    private const ENDPOINT = 'https://api.indexnow.org/indexnow';

    public function __construct(private SeoSettings $settings) {}

    public function enabled(): bool
    {
        return (bool) $this->settings->get('indexnow_enabled') && preg_match('/^[a-f0-9]{32}$/', (string) $this->settings->get('indexnow_key')) === 1;
    }

    /** @param list<string> $urls */
    public function submit(array $urls): bool
    {
        $host = $this->settings->canonicalHost();
        $urls = array_values(array_filter($urls, fn (string $url) => strtolower((string) parse_url($url, PHP_URL_HOST)) === $host));

        if (! $this->enabled() || $urls === [] || ! $this->settings->indexingAllowed(Request::create($this->settings->url('/')))) {
            return false;
        }

        $key = (string) $this->settings->get('indexnow_key');
        $response = Http::timeout(10)->acceptJson()->post(self::ENDPOINT, [
            'host' => $host,
            'key' => $key,
            'keyLocation' => $this->settings->url('/'.$key.'.txt'),
            'urlList' => array_slice($urls, 0, 10000),
        ]);

        if (! in_array($response->status(), [200, 202], true)) {
            Log::warning('seo.indexnow.failed', ['status' => $response->status()]);

            return false;
        }

        return true;
    }
}
