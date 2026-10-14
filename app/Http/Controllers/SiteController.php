<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use App\Models\PortfolioItem;
use App\Models\Testimonial;
use App\Support\PublicPages;
use App\Support\Seo;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Páginas institucionais do site público. O SEO de cada página vem de
 * PublicPages; o conteúdo, de resources/js/data/site.ts.
 */
class SiteController extends Controller
{
    public function home(): Response
    {
        return Inertia::render('welcome', [
            'blogPosts' => BlogPost::query()->published()->with('category:id,name,slug')->latest('published_at')->limit(3)->get(['id', 'blog_category_id', 'title', 'slug', 'excerpt', 'published_at']),
            'testimonials' => Testimonial::query()->published()->orderBy('sort_order')->latest()->limit(6)->get(['id', 'author_name', 'author_role', 'photo_url', 'quote']),
            'portfolioItems' => PortfolioItem::query()->published()->orderBy('sort_order')->latest()->limit(6)->get(['id', 'title', 'description', 'screenshot_url', 'site_url']),
            'seo' => Seo::tags([
                'title' => 'ABrasil Sistemas — Software, sistemas e integração para empresas',
                'description' => 'Empresa de tecnologia que desenvolve sistemas sob medida, aplicativos, sites, automações e integrações para empresas, além dos produtos VetorOS e VetorPet.',
                'canonical' => url('/'),
                'jsonLd' => [
                    '@context' => 'https://schema.org',
                    '@graph' => [
                        PublicPages::organization(),
                        [
                            '@type' => 'WebSite',
                            'name' => 'ABrasil Sistemas',
                            'url' => url('/'),
                            'inLanguage' => 'pt-BR',
                            'publisher' => ['@id' => url('/').'#organization'],
                        ],
                    ],
                ],
            ]),
        ]);
    }

    public function systems(): Response
    {
        return Inertia::render('site/sistemas', [
            'seo' => Seo::tags([
                'title' => 'Sistemas prontos para empresas — VetorOS e VetorPet | ABrasil Sistemas',
                'description' => 'Produtos de software da ABrasil Sistemas em operação: VetorOS para assistências técnicas e VetorPet para a gestão comercial no mercado pet.',
                'canonical' => url('/sistemas'),
                'jsonLd' => [
                    '@context' => 'https://schema.org',
                    '@graph' => [
                        PublicPages::organization(),
                        ...array_map(
                            fn (string $slug) => PublicPages::productJsonLd($slug)['@graph'][1],
                            array_keys(PublicPages::PRODUCTS),
                        ),
                    ],
                ],
            ]),
        ]);
    }

    public function system(string $product): Response
    {
        abort_unless(isset(PublicPages::PRODUCTS[$product]), 404);

        $meta = PublicPages::PRODUCTS[$product];

        return Inertia::render('site/sistema', [
            'slug' => $product,
            'seo' => Seo::tags([
                'title' => $meta['title'],
                'description' => $meta['description'],
                'canonical' => url('/sistemas/'.$product),
                'jsonLd' => PublicPages::productJsonLd($product),
            ]),
        ]);
    }

    public function services(): Response
    {
        return Inertia::render('servicos', [
            'seo' => Seo::tags([
                'title' => 'Soluções — sistemas, aplicativos, sites, automação e integrações | ABrasil Sistemas',
                'description' => 'Desenvolvimento de sistemas sob medida, aplicativos, sites, landing pages, automação de processos e integração de sistemas para empresas.',
                'canonical' => url('/servicos'),
                'jsonLd' => ['@context' => 'https://schema.org', '@graph' => [PublicPages::organization()]],
            ]),
        ]);
    }

    public function service(Request $request): Response
    {
        $slug = trim($request->path(), '/');

        abort_unless(isset(PublicPages::SERVICES[$slug]), 404);

        $meta = PublicPages::SERVICES[$slug];

        return Inertia::render('site/servico', [
            'slug' => $slug,
            'seo' => Seo::tags([
                'title' => $meta['title'],
                'description' => $meta['description'],
                'canonical' => url('/'.$slug),
                'jsonLd' => PublicPages::serviceJsonLd($slug),
            ]),
        ]);
    }

    public function about(): Response
    {
        return Inertia::render('sobre', [
            'seo' => Seo::tags([
                'title' => 'Sobre a ABrasil Sistemas — empresa de tecnologia e software',
                'description' => 'Conheça a ABrasil Sistemas: como trabalhamos, o que desenvolvemos e por que acompanhamos cada sistema depois que ele entra em operação.',
                'canonical' => url('/sobre'),
                'jsonLd' => ['@context' => 'https://schema.org', '@graph' => [PublicPages::organization()]],
            ]),
        ]);
    }
}
