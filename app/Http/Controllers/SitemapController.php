<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use App\Support\PublicPages;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $urls = PublicPages::sitemapUrls();

        BlogPost::query()
            ->published()
            ->orderByDesc('published_at')
            ->get(['slug', 'published_at', 'updated_at'])
            ->each(function (BlogPost $post) use (&$urls) {
                $urls[] = [
                    'loc' => url('/blog/'.$post->slug),
                    'lastmod' => $post->updated_at?->toAtomString(),
                    'changefreq' => 'monthly',
                    'priority' => '0.6',
                ];
            });

        $xml = view('sitemap', ['urls' => $urls])->render();

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }
}
