<?php

use App\Models\Lead;
use App\Support\PublicPages;
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
        $this->get('/'.$slug)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('site/servico')
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
