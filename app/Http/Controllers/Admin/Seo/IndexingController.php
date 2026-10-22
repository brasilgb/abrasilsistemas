<?php

namespace App\Http\Controllers\Admin\Seo;

use App\Http\Controllers\Controller;
use App\Services\Seo\GoogleIntegration;
use App\Services\Seo\RobotsBuilder;
use App\Services\Seo\SeoSettings;
use App\Services\Seo\SitemapBuilder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Sitemap e indexação: o que entra no sitemap (e por que algo fica de fora) e o robots.txt. */
class IndexingController extends Controller
{
    public function __invoke(SitemapBuilder $sitemap, RobotsBuilder $robots, SeoSettings $settings, GoogleIntegration $google): Response
    {
        $entries = $sitemap->entries();

        return Inertia::render('admin/seo/indexing', [
            'entries' => $entries->map(fn (array $entry) => collect($entry)->except('sitemap')->all())->values(),
            'counts' => ['included' => $entries->where('included', true)->count(), 'excluded' => $entries->where('included', false)->count()],
            'sitemapUrl' => $settings->url('/sitemap.xml'),
            'robotsUrl' => $settings->url('/robots.txt'),
            // Prévia como o domínio canônico recebe (o painel pode estar em outro host).
            'robots' => $robots->build(Request::create($settings->url('/robots.txt'))),
            'privatePaths' => RobotsBuilder::PRIVATE_PATHS,
            'blockIndexing' => (bool) $settings->get('block_indexing') || (bool) config('seo.block_indexing'),
            'blockedByEnvironment' => (bool) config('seo.block_indexing'),
            'googleConnected' => $google->connected(),
        ]);
    }
}
