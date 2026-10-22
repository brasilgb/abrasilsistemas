<?php

namespace App\Http\Controllers;

use App\Services\Seo\SeoSettings;
use App\Services\Seo\SitemapBuilder;
use Illuminate\Http\Response;

/**
 * /sitemap.xml dinâmico. Até o limite de URLs por arquivo sai um urlset único; acima
 * dele, /sitemap.xml vira um índice que aponta para /sitemaps/{n}.xml.
 */
class SitemapController extends Controller
{
    public function __construct(private SitemapBuilder $builder, private SeoSettings $settings) {}

    public function index(): Response
    {
        $urls = $this->builder->urls();
        $limit = $this->builder->limit();

        if (count($urls) <= $limit) {
            return $this->xml(view('sitemap', ['urls' => $urls])->render());
        }

        $parts = array_map(fn (int $part) => [
            'loc' => $this->settings->url('/sitemaps/'.$part.'.xml'),
        ], range(1, (int) ceil(count($urls) / $limit)));

        return $this->xml(view('sitemap-index', ['sitemaps' => $parts])->render());
    }

    public function part(int $part): Response
    {
        $chunk = array_chunk($this->builder->urls(), $this->builder->limit())[$part - 1] ?? null;

        abort_if($chunk === null || $part < 1, 404);

        return $this->xml(view('sitemap', ['urls' => $chunk])->render());
    }

    private function xml(string $xml): Response
    {
        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8', 'X-Robots-Tag' => 'noindex']);
    }
}
