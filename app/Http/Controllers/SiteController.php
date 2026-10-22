<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use App\Models\PortfolioItem;
use App\Models\Testimonial;
use App\Services\Seo\StructuredData;
use App\Support\PublicPages;
use App\Support\Seo;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Páginas institucionais do site público. O SEO padrão de cada página vem de
 * PublicPages (e pode ser ajustado em Painel → SEO → Páginas); o conteúdo, de
 * resources/js/data/site.ts.
 */
class SiteController extends Controller
{
    public function __construct(private StructuredData $structuredData) {}

    public function home(): Response
    {
        return Inertia::render('welcome', [
            'blogPosts' => BlogPost::query()->published()->with('category:id,name,slug')->latest('published_at')->limit(3)->get(['id', 'blog_category_id', 'title', 'slug', 'excerpt', 'published_at']),
            'testimonials' => Testimonial::query()->published()->orderBy('sort_order')->latest()->limit(6)->get(['id', 'author_name', 'author_role', 'photo_url', 'quote']),
            'portfolioItems' => PortfolioItem::query()->published()->orderBy('sort_order')->latest()->limit(6)->get(['id', 'title', 'description', 'screenshot_url', 'site_url']),
            'seo' => Seo::page('home', ['jsonLd' => $this->structuredData->forPage('home')]),
        ]);
    }

    public function systems(): Response
    {
        return Inertia::render('site/sistemas', [
            'seo' => Seo::page('sistemas', ['jsonLd' => $this->structuredData->forPage('sistemas')]),
        ]);
    }

    public function system(string $product): Response
    {
        abort_unless(isset(PublicPages::PRODUCTS[$product]), 404);

        return Inertia::render('site/sistema', [
            'slug' => $product,
            'seo' => Seo::page('sistema:'.$product, ['jsonLd' => $this->structuredData->forPage('sistema:'.$product)]),
        ]);
    }

    public function services(): Response
    {
        return Inertia::render('servicos', [
            'seo' => Seo::page('servicos', ['jsonLd' => $this->structuredData->forPage('servicos')]),
        ]);
    }

    public function service(Request $request): Response
    {
        $slug = trim($request->path(), '/');

        abort_unless(isset(PublicPages::SERVICES[$slug]), 404);

        // O SEO profissional tem landing page própria; os demais serviços usam o modelo comum.
        return Inertia::render($slug === PublicPages::SEO_SERVICE ? 'site/seo' : 'site/servico', [
            'slug' => $slug,
            'seo' => Seo::page('servico:'.$slug, ['jsonLd' => $this->structuredData->forPage('servico:'.$slug)]),
        ]);
    }

    public function about(): Response
    {
        return Inertia::render('sobre', [
            'seo' => Seo::page('sobre', ['jsonLd' => $this->structuredData->forPage('sobre')]),
        ]);
    }

    /** Páginas legais: noindex por padrão (podem ser alteradas no painel). */
    public function legal(Request $request): Response
    {
        $page = (string) $request->route()->defaults['page'];

        return Inertia::render('legal/'.$page, ['seo' => Seo::page($page)]);
    }
}
