<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $urls = [
            ['loc' => url('/'), 'changefreq' => 'weekly', 'priority' => '1.0'],
            ['loc' => url('/desenvolvimento-de-sites-para-empresas'), 'changefreq' => 'monthly', 'priority' => '0.9'],
            ['loc' => url('/blog'), 'changefreq' => 'weekly', 'priority' => '0.7'],
        ];

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
