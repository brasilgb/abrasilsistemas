<?php

use App\Models\Lead;
use App\Support\PublicPages;
use App\Support\Seo;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

test('institutional pages render with their own seo', function (string $path, string $component) {
    $this->get($path)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component($component)
            ->where('seo.canonical', url($path === '/' ? '/' : $path))
            ->whereType('seo.title', 'string')
            ->whereType('seo.description', 'string')
            ->has('seo.jsonLd'));
})->with([
    'home' => ['/', 'welcome'],
    'sistemas' => ['/sistemas', 'site/sistemas'],
    'vetoros' => ['/sistemas/vetoros', 'site/sistema'],
    'vetorpet' => ['/sistemas/vetorpet', 'site/sistema'],
    'soluções' => ['/servicos', 'servicos'],
    'sobre' => ['/sobre', 'sobre'],
    'contato' => ['/contato', 'contato'],
]);

test('every service page renders the shared service component with its slug', function () {
    foreach (array_keys(PublicPages::SERVICES) as $slug) {
        // O SEO profissional é a única landing page própria.
        $this->get('/'.$slug)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component($slug === PublicPages::SEO_SERVICE ? 'site/seo' : 'site/servico')
                ->where('slug', $slug)
                ->where('seo.title', PublicPages::SERVICES[$slug]['title'])
                ->where('seo.canonical', url('/'.$slug)));
    }
});

test('the existing company websites url and route name are preserved', function () {
    expect(route('company-websites', absolute: false))->toBe('/desenvolvimento-de-sites-para-empresas');

    $this->get('/desenvolvimento-de-sites-para-empresas')->assertOk();
});

test('unknown products return 404', function () {
    $this->get('/sistemas/nao-existe')->assertNotFound();
});

test('the old products page redirects permanently to the systems page', function () {
    $this->get('/produtos')->assertStatus(301)->assertRedirect('/sistemas');
});

test('the initial html carries one description, canonical and json-ld per page', function () {
    $html = $this->get('/automacao-de-processos')->assertOk()->getContent();

    expect(substr_count($html, 'name="description"'))->toBe(1)
        ->and(substr_count($html, 'rel="canonical"'))->toBe(1)
        ->and($html)->toContain('data-inertia="description"')
        ->and($html)->toContain('<script type="application/ld+json" data-inertia="json-ld">')
        ->and($html)->toContain('"@type":"Service"')
        ->and($html)->toContain('<meta property="og:url" content="'.url('/automacao-de-processos').'"');
});

test('json-ld cannot break out of its script tag', function () {
    $html = $this->get('/')->getContent();
    preg_match('#<script type="application/ld\+json"[^>]*>(.*?)</script>#s', $html, $matches);

    expect($matches[1] ?? '')->not->toContain('<')
        ->and(json_decode($matches[1], true))->toBeArray();
});

test('the sitemap lists every institutional page and drops the old products url', function () {
    $xml = $this->get('/sitemap.xml')->assertOk()->getContent();

    foreach (PublicPages::sitemapUrls() as $url) {
        expect($xml)->toContain('<loc>'.$url['loc'].'</loc>');
    }

    expect($xml)->toContain('<loc>'.url('/sistemas/vetoros').'</loc>')
        ->and($xml)->toContain('<loc>'.url('/integracao-de-sistemas-e-apis').'</loc>')
        ->and($xml)->not->toContain('<loc>'.url('/produtos').'</loc>');
});

test('contact page lists the project types and preselects a valid tipo', function () {
    $this->get('/contato?tipo=automacao')
        ->assertInertia(fn (Assert $page) => $page
            ->where('projectTypes', Lead::CONTACT_PROJECT_TYPES)
            ->where('selectedType', 'automacao'));

    $this->get('/contato?tipo=invalido')
        ->assertInertia(fn (Assert $page) => $page->where('selectedType', null));
});

test('contact form accepts the automation project type', function () {
    Queue::fake();

    $this->post(route('contato.store'), [
        'contact_name' => 'Ana',
        'company_name' => 'Indústria Exemplo',
        'email' => 'ana@exemplo.test',
        'product' => 'automacao',
        'notes' => 'Queremos automatizar a cobrança.',
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect(Lead::query()->where('product', 'automacao')->where('source', 'site')->exists())->toBeTrue();
});

test('contact form rejects project types outside the public list', function () {
    $this->post(route('contato.store'), [
        'contact_name' => 'Ana',
        'company_name' => 'Indústria Exemplo',
        'email' => 'ana@exemplo.test',
        'product' => 'inexistente',
        'notes' => 'Teste.',
    ])->assertSessionHasErrors('product');
});

test('every public project type is a valid crm product', function () {
    expect(array_diff(array_keys(Lead::CONTACT_PROJECT_TYPES), array_keys(Lead::PRODUCTS)))->toBe([]);
});

test('each institutional page shares its own hero photo with alt text', function (string $path, string $hero) {
    $this->get($path)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('seo.ogImage', url("/images/site/heroes/{$hero}-og.jpg"))
            ->where('seo.ogImageAlt', PublicPages::HERO_IMAGES[$hero]));
})->with([
    'home' => ['/', 'home'],
    'sistemas prontos' => ['/sistemas', 'sistemas'],
    'sob medida' => ['/desenvolvimento-de-sistemas', 'sistemas-sob-medida'],
    'automação' => ['/automacao-de-processos', 'automacao'],
    'integrações' => ['/integracao-de-sistemas-e-apis', 'integracoes'],
    'aplicativos' => ['/desenvolvimento-de-aplicativos', 'aplicativos'],
    'sites' => ['/desenvolvimento-de-sites-para-empresas', 'sites'],
    'landing pages' => ['/criacao-de-landing-pages', 'landing-pages'],
    'seo' => ['/seo-tecnico-e-otimizacao-de-sites', 'seo'],
    'vetoros' => ['/sistemas/vetoros', 'vetoros'],
    'vetorpet' => ['/sistemas/vetorpet', 'vetorpet'],
    'sobre' => ['/sobre', 'sobre'],
    'contato' => ['/contato', 'contato'],
]);

test('the initial html carries og:image and its alt text', function () {
    $html = $this->get('/automacao-de-processos')->assertOk()->getContent();

    expect($html)
        ->toContain('<meta property="og:image" content="'.url('/images/site/heroes/automacao-og.jpg').'"')
        ->toContain('<meta property="og:image:alt" content="'.e(PublicPages::HERO_IMAGES['automacao']).'"');
});

test('every hero photo exists in every responsive size and format', function () {
    foreach (array_keys(PublicPages::HERO_IMAGES) as $key) {
        foreach ([640, 1280, 1920] as $width) {
            foreach (['avif', 'webp'] as $format) {
                $file = public_path("images/site/heroes/{$key}-{$width}.{$format}");

                expect($file)->toBeFile();
                expect(filesize($file))->toBeLessThan(250 * 1024);
            }
        }

        [$width, $height] = getimagesize(public_path("images/site/heroes/{$key}-og.jpg"));

        expect([$width, $height])->toBe([1200, 630]);
    }
});

test('no hero photo is reused on another page', function () {
    $hashes = collect(array_keys(PublicPages::HERO_IMAGES))
        ->map(fn (string $key) => md5_file(public_path("images/site/heroes/{$key}-640.webp")));

    expect($hashes->unique())->toHaveCount(count(PublicPages::HERO_IMAGES))
        ->and(array_unique(PublicPages::SERVICE_HERO_IMAGES))->toHaveCount(count(PublicPages::SERVICES));
});

test('hero alt texts are the same on the server and in the frontend', function () {
    $frontend = file_get_contents(resource_path('js/data/hero-images.ts'));

    foreach (PublicPages::HERO_IMAGES as $key => $alt) {
        expect($frontend)->toContain("key: '{$key}'")->toContain("alt: '{$alt}'");
    }
});

test('a blog post with its own cover does not inherit the default image alt', function () {
    $tags = Seo::tags(['ogImage' => 'https://exemplo.test/capa.jpg']);

    expect($tags)->not->toHaveKey('ogImageAlt');
    expect(Seo::tags()['ogImageAlt'])->toBe(PublicPages::HERO_IMAGES['home']);
});

test('the not found page renders inside the institutional layout', function () {
    $this->get('/pagina-que-nao-existe')
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page->component('errors/404'));
});
