<?php

use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\Lead;
use App\Models\SeoMeta;
use App\Models\User;
use App\Services\Seo\StructuredData;
use App\Support\PublicPages;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

// ABRASIL-SEO-FINAL-01: serviço de SEO profissional, SEO técnico incluído e pautas do blog.

beforeEach(function () {
    config(['app.url' => 'http://localhost', 'seo.block_indexing' => false]);
});

test('a landing page de SEO tem SEO completo e JSON-LD sem preço nem avaliação', function () {
    $path = '/'.PublicPages::SEO_SERVICE;

    $this->get($path)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('site/seo')
            ->where('seo.title', PublicPages::SERVICES[PublicPages::SEO_SERVICE]['title'])
            ->where('seo.canonical', 'http://localhost'.$path)
            ->where('seo.robots', fn ($robots) => ! str_contains((string) $robots, 'noindex'))
            ->where('seo.ogImage', 'http://localhost/images/site/heroes/seo-og.jpg'));

    $jsonLd = app(StructuredData::class)->forPage('servico:'.PublicPages::SEO_SERVICE);
    $service = collect($jsonLd['@graph'])->firstWhere('@type', 'Service');
    $encoded = json_encode($jsonLd);

    expect(StructuredData::validate($jsonLd))->toBe([])
        ->and(collect($service['hasOfferCatalog']['itemListElement'])->pluck('itemOffered.name')->all())
        ->toBe(['SEO Essencial', 'SEO Profissional', 'SEO Gerenciado'])
        ->and($encoded)->not->toContain('"price')
        ->and($encoded)->not->toContain('aggregateRating');
});

test('o HTML inicial da página de SEO já traz título, canonical e JSON-LD', function () {
    $html = $this->get('/'.PublicPages::SEO_SERVICE)->assertOk()->getContent();

    expect($html)
        ->toContain('<link rel="canonical" href="http://localhost/'.PublicPages::SEO_SERVICE.'"')
        ->toContain('application/ld+json')
        ->toContain('OfferCatalog')
        ->not->toContain('content="noindex');
});

test('a página de SEO entra no sitemap e o robots aponta para ele', function () {
    $this->get('/sitemap.xml')->assertOk()->assertSee('http://localhost/'.PublicPages::SEO_SERVICE, false);
    $this->get('/robots.txt')->assertOk()
        ->assertSee('Sitemap: http://localhost/sitemap.xml')
        ->assertSee('Disallow: /admin')
        ->assertDontSee("Disallow: /\n", false);
});

test('fora do domínio canônico ou com bloqueio ligado o site não é indexado', function () {
    config(['seo.block_indexing' => true]);

    $this->get('/'.PublicPages::SEO_SERVICE)->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('seo.robots', fn ($robots) => str_contains((string) $robots, 'noindex')));
});

test('o formulário pré-seleciona e registra o interesse em SEO Profissional', function () {
    Queue::fake();

    $this->get('/contato?tipo=seo')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('selectedType', 'seo')->where('projectTypes.seo', 'SEO Profissional'));

    $this->post(route('contato.store'), [
        'contact_name' => 'Bruna',
        'company_name' => 'Loja Exemplo',
        'email' => 'bruna@exemplo.test',
        'product' => 'seo',
        'notes' => 'Nosso site é lojaexemplo.com.br e não aparece no Google.',
        'source_page' => '/'.PublicPages::SEO_SERVICE,
    ])->assertRedirect()->assertSessionHasNoErrors();

    $lead = Lead::query()->where('product', 'seo')->sole();

    expect($lead->source)->toBe(Lead::SOURCE_SITE)
        ->and($lead->source_page)->toBe('/'.PublicPages::SEO_SERVICE);

    $this->actingAs(User::factory()->create())->get(route('solicitacoes.show', $lead))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('request.project_type', 'SEO Profissional'));
});

test('a categoria SEO existe e as pautas viram rascunho sem duplicar', function () {
    $admin = User::factory()->create();
    $category = BlogCategory::query()->where('slug', 'seo-e-performance')->sole();

    BlogPost::create([
        'user_id' => $admin->id, 'title' => 'Como aparecer nas pesquisas do Google', 'slug' => 'aparecer-no-google', 'excerpt' => 'Resumo.',
        'body' => '<p>Artigo já existente.</p>', 'status' => 'published', 'published_at' => now()->subDay(),
    ]);

    $this->artisan('blog:seo-drafts')->assertSuccessful();

    $drafts = BlogPost::query()->where('blog_category_id', $category->id)->get();

    expect($drafts)->toHaveCount(4)
        ->and($drafts->pluck('status')->unique()->all())->toBe(['draft'])
        ->and($drafts->whereNotNull('published_at'))->toBeEmpty()
        ->and(BlogPost::query()->where('title', 'Como aparecer nas pesquisas do Google?')->exists())->toBeFalse();

    $meta = SeoMeta::for(SeoMeta::TYPE_POST, $drafts->first()->id);
    expect($meta->search_intent)->toBe('seo')
        ->and($meta->related_pages)->toBe(['page:servico:'.PublicPages::SEO_SERVICE]);

    // Rascunho não é público nem entra no sitemap.
    $this->get('/blog/'.$drafts->first()->slug)->assertNotFound();
    $this->get('/sitemap.xml')->assertDontSee($drafts->first()->slug);

    // Rodar de novo não cria nada.
    $this->artisan('blog:seo-drafts')->assertSuccessful();
    expect(BlogPost::query()->count())->toBe(5);
});

test('as páginas de projetos web anunciam o SEO técnico incluído', function () {
    $site = file_get_contents(resource_path('js/data/site.ts'));

    expect($site)->toContain('SEO técnico incluído em todos os nossos projetos web.');

    foreach (['desenvolvimento-de-sistemas', 'desenvolvimento-de-sites-para-empresas', 'criacao-de-landing-pages'] as $slug) {
        expect(preg_match("/slug: '{$slug}'.*?seoIncluded: true,\n    \}/s", $site))->toBe(1);
    }
});

test('seo:check aprova a produção e acusa APP_URL errado ou bloqueio ligado', function () {
    config(['app.url' => 'https://abrasilsistemas.com.br']);
    $this->artisan('seo:check')->expectsOutputToContain('Pronto para indexação.')->assertSuccessful();

    // O padrão do compose quando ABRASILSISTEMA_APP_URL não está definido.
    config(['app.url' => 'http://abrasilsistema.localhost']);
    $this->artisan('seo:check')->expectsOutputToContain('não é público')->assertFailed();

    config(['app.url' => 'https://abrasilsistemas.com.br', 'seo.block_indexing' => true]);
    $this->artisan('seo:check')->expectsOutputToContain('SEO_BLOCK_INDEXING=true')->assertFailed();
});
