<?php

use App\Models\BlogPost;
use App\Models\SeoAudit;
use App\Models\SeoMeta;
use App\Models\SeoRedirect;
use App\Models\Setting;
use App\Models\User;
use App\Services\Seo\SeoSettings;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    // Os testes acessam http://localhost: esse precisa ser o domínio canônico para o site ser indexável.
    config(['app.url' => 'http://localhost', 'seo.block_indexing' => false]);
});

function seoPost(array $attributes = []): BlogPost
{
    $author = User::query()->first() ?? User::factory()->create();

    return BlogPost::create([
        'user_id' => $author->id,
        'title' => 'Como automatizar cobranças',
        'slug' => 'como-automatizar-cobrancas',
        'excerpt' => 'Um guia prático para automatizar a cobrança de clientes.',
        'body' => '<p>Conteúdo do artigo.</p>',
        'status' => 'published',
        'published_at' => now()->subDay(),
        ...$attributes,
    ]);
}

test('somente administradores acessam o painel de SEO', function () {
    $this->get('/admin/seo')->assertRedirect(route('login'));
    $this->actingAs(User::factory()->reader()->create())->get('/admin/seo')->assertForbidden();
});

test('administrador abre todas as telas de SEO', function (string $url, string $component) {
    seoPost();

    $this->actingAs(User::factory()->create())->get($url)->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component($component));
})->with([
    ['/admin/seo', 'admin/seo/overview'],
    ['/admin/seo/auditoria', 'admin/seo/audits/index'],
    ['/admin/seo/paginas', 'admin/seo/pages/index'],
    ['/admin/seo/paginas/page:sobre', 'admin/seo/pages/edit'],
    ['/admin/seo/palavras-chave', 'admin/seo/keywords'],
    ['/admin/seo/sitemap', 'admin/seo/indexing'],
    ['/admin/seo/redirecionamentos', 'admin/seo/redirects/index'],
    ['/admin/seo/redirecionamentos/create', 'admin/seo/redirects/form'],
    ['/admin/seo/dados-estruturados', 'admin/seo/structured-data'],
    ['/admin/seo/integracoes', 'admin/seo/integrations'],
    ['/admin/seo/configuracoes', 'admin/seo/settings'],
]);

test('integrações sem conexão não exibem dados', function () {
    Http::fake();

    $this->actingAs(User::factory()->create())->get('/admin/seo/integracoes')->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('google.connected', false)
            ->missing('searchConsole')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('searchConsole', null)
                ->where('analytics', null)
                ->where('sites', null)));

    Http::assertNothingSent();
});

test('configurações validam o modelo de título e o robots', function () {
    $admin = User::factory()->create();
    $settings = app(SeoSettings::class)->all();
    $payload = [...$settings, 'title_template' => 'Sem marcador'];

    $this->actingAs($admin)->put('/admin/seo/configuracoes', $payload)->assertSessionHasErrors('title_template');
    $this->actingAs($admin)->put('/admin/seo/configuracoes', [...$settings, 'robots_disallow' => ['/']])->assertSessionHasErrors('robots_disallow.0');

    $this->actingAs($admin)->put('/admin/seo/configuracoes', [...$settings, 'title_template' => '%title% — %site%', 'robots_disallow' => ['/rascunhos']])
        ->assertRedirect('/admin/seo/configuracoes');

    $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /rascunhos')->assertSee('Disallow: /admin')->assertSee('Sitemap: http://localhost/sitemap.xml');
});

test('bloquear a indexação esvazia o sitemap e fecha o robots', function () {
    seoPost();
    app(SeoSettings::class)->save(['block_indexing' => true]);

    $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /')->assertDontSee('Sitemap:');
    $this->get('/sitemap.xml')->assertOk()->assertDontSee('como-automatizar-cobrancas');
});

test('sitemap só lista URLs canônicas e indexáveis', function () {
    $public = seoPost();
    seoPost(['title' => 'Rascunho', 'slug' => 'rascunho', 'status' => 'draft', 'published_at' => null]);
    $hidden = seoPost(['title' => 'Oculto', 'slug' => 'artigo-oculto']);
    SeoMeta::create(['subject_type' => SeoMeta::TYPE_POST, 'subject_id' => (string) $hidden->id, 'robots_index' => false]);

    $this->get('/sitemap.xml')->assertOk()
        ->assertSee('http://localhost/blog/'.$public->slug)
        ->assertSee('http://localhost/sobre')
        ->assertDontSee('/blog/rascunho')
        ->assertDontSee('/blog/artigo-oculto');

    $this->get('/blog/artigo-oculto')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('seo.robots', fn ($robots) => str_contains($robots, 'noindex')));
});

test('metadados da página sobrescrevem o padrão e noindex explícito é respeitado', function () {
    $this->actingAs(User::factory()->create())->put('/admin/seo/paginas/page:sobre', [
        'meta_title' => 'Quem somos | ABrasil',
        'meta_description' => 'Conheça a equipe da ABrasil Sistemas.',
        'robots_index' => false,
    ])->assertRedirect('/admin/seo/paginas/page:sobre');

    $this->get('/sobre')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('seo.title', 'Quem somos | ABrasil')
        ->where('seo.description', 'Conheça a equipe da ABrasil Sistemas.')
        ->where('seo.robots', fn ($robots) => str_starts_with($robots, 'noindex')));

    $this->get('/sitemap.xml')->assertDontSee('http://localhost/sobre<');
});

test('redirecionamentos 301 e 410 funcionam e loops são bloqueados', function () {
    $admin = User::factory()->create();

    $this->actingAs($admin)->post('/admin/seo/redirecionamentos', ['source_path' => '/servicos-antigos', 'target_url' => '/servicos', 'status_code' => 301, 'is_active' => true])
        ->assertRedirect('/admin/seo/redirecionamentos');
    $this->actingAs($admin)->post('/admin/seo/redirecionamentos', ['source_path' => '/pagina-removida', 'status_code' => 410, 'is_active' => true])
        ->assertSessionHasNoErrors();

    $this->get('/servicos-antigos?utm=1')->assertStatus(301)->assertRedirect('/servicos?utm=1');
    $this->get('/pagina-removida')->assertStatus(410);
    expect(SeoRedirect::query()->where('source_path', '/servicos-antigos')->value('hits'))->toBe(1);

    // /servicos → /servicos-antigos fecharia um loop.
    $this->actingAs($admin)->post('/admin/seo/redirecionamentos', ['source_path' => '/servicos', 'target_url' => '/servicos-antigos', 'status_code' => 301, 'is_active' => true])
        ->assertSessionHasErrors('target_url');
    // Áreas privadas não podem ser origem.
    $this->actingAs($admin)->post('/admin/seo/redirecionamentos', ['source_path' => '/admin/blog', 'target_url' => '/blog', 'status_code' => 301, 'is_active' => true])
        ->assertSessionHasErrors('source_path');

    $redirect = SeoRedirect::query()->where('source_path', '/servicos-antigos')->firstOrFail();
    $this->actingAs($admin)->put("/admin/seo/redirecionamentos/{$redirect->id}", ['source_path' => '/servicos-antigos', 'target_url' => '/servicos', 'status_code' => 301, 'is_active' => false])
        ->assertRedirect();
    $this->get('/servicos-antigos')->assertNotFound();
    $this->assertDatabaseHas('seo_change_logs', ['entity_type' => 'redirect', 'entity_id' => (string) $redirect->id, 'action' => 'updated']);
});

test('trocar o slug de um artigo publicado cria 301 e excluir responde 410', function () {
    $admin = User::factory()->create();
    $post = seoPost();
    $payload = ['title' => 'Novo título', 'excerpt' => $post->excerpt, 'body' => $post->body, 'status' => 'published', 'published_at' => '', 'featured' => false, 'tags' => []];

    // Trocar só o título não muda o endereço publicado.
    $this->actingAs($admin)->put("/admin/blog/posts/{$post->id}", [...$payload, 'slug' => $post->slug])->assertRedirect();
    expect($post->fresh()->slug)->toBe('como-automatizar-cobrancas');

    $this->actingAs($admin)->put("/admin/blog/posts/{$post->id}", [...$payload, 'slug' => 'automacao-de-cobrancas', 'seo' => ['focus_keyword' => 'automação de cobranças']])
        ->assertRedirect();

    expect($post->fresh()->slug)->toBe('automacao-de-cobrancas');
    expect(SeoMeta::for(SeoMeta::TYPE_POST, $post->id)?->focus_keyword)->toBe('automação de cobranças');
    $this->get('/blog/como-automatizar-cobrancas')->assertStatus(301)->assertRedirect('/blog/automacao-de-cobrancas');

    $this->actingAs($admin)->put("/admin/blog/posts/{$post->id}", [...$payload, 'slug' => 'Slug Inválido'])->assertSessionHasErrors('slug');

    $this->actingAs($admin)->delete("/admin/blog/posts/{$post->id}")->assertRedirect();
    $this->get('/blog/automacao-de-cobrancas')->assertStatus(410);
});

test('artigo publica JSON-LD BlogPosting sem dados inventados', function () {
    $post = seoPost();

    $this->get('/blog/'.$post->slug)->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('seo.canonical', 'http://localhost/blog/'.$post->slug)
        ->where('seo.jsonLd', function ($jsonLd) {
            $json = json_encode($jsonLd);

            return str_contains($json, 'BlogPosting') && ! str_contains($json, 'aggregateRating') && ! str_contains($json, '"offers"');
        }));
});

test('metatags e JSON-LD são escapados no HTML inicial', function () {
    seoPost(['title' => 'Título </script><script>alert(1)</script>', 'slug' => 'xss']);

    $html = $this->get('/blog/xss')->assertOk()->getContent();

    expect($html)->not->toContain('<script>alert(1)</script>');
});

test('áreas privadas respondem com X-Robots-Tag noindex', function () {
    $this->get('/login')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    $this->get('/sobre')->assertHeaderMissing('X-Robots-Tag');
});

test('análise editorial devolve pontuação interna de 0 a 100', function () {
    $response = $this->actingAs(User::factory()->create())->postJson('/admin/seo/analisar', [
        'title' => 'Como automatizar cobranças',
        'body' => '<h2>Por que automatizar</h2><p>Texto do artigo sobre automação de cobranças.</p><img src="/a.png">',
        'focus_keyword' => 'automatizar cobranças',
    ])->assertOk();

    expect($response->json('score'))->toBeInt()->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(100);
    expect($response->json('checks'))->not->toBeEmpty();
    expect($response->json('disclaimer'))->toContain('Google');
});

test('auditoria acessa só o host permitido e registra problemas reais', function () {
    config(['seo.audit.base_url' => 'http://interno.test']);
    Http::fake([
        'interno.test/*' => Http::response('<html><head><title>Página</title></head><body><p>Sem título principal.</p><img src="/x.png"></body></html>', 200, ['Content-Type' => 'text/html']),
        '*' => Http::response('não deveria ser acessado', 500),
    ]);

    $this->actingAs(User::factory()->create())->post('/admin/seo/auditoria')->assertRedirect();

    $audit = SeoAudit::query()->latest('id')->firstOrFail();
    expect($audit->status)->toBe(SeoAudit::STATUS_COMPLETED);
    expect($audit->issues()->where('code', 'missing_h1')->exists())->toBeTrue();
    Http::assertNotSent(fn ($request) => ! str_starts_with($request->url(), 'http://interno.test'));
});

test('GA4 só é compartilhado com o site quando configurado', function () {
    $this->get('/sobre')->assertInertia(fn (Assert $page) => $page->where('analytics', null));

    $this->actingAs(User::factory()->create())->put('/admin/seo/integracoes', ['ga4_measurement_id' => 'G-ABC1234', 'ga4_events' => ['generate_lead'], 'indexnow_enabled' => false])
        ->assertRedirect('/admin/seo/integracoes');

    $this->get('/sobre')->assertInertia(fn (Assert $page) => $page
        ->where('analytics.measurementId', 'G-ABC1234')
        ->where('analytics.events', ['generate_lead']));

    $this->actingAs(User::factory()->create())->put('/admin/seo/integracoes', ['ga4_measurement_id' => '<script>'])
        ->assertSessionHasErrors('ga4_measurement_id');
});

test('Search Console conectado mostra os dados da API e erros de acesso', function () {
    config(['seo.google.client_id' => 'id', 'seo.google.client_secret' => 'secret']);
    Setting::query()->create(['key' => Setting::SEO_GSC_TOKEN, 'value' => json_encode([
        'refresh_token' => 'refresh', 'access_token' => 'access', 'expires_at' => now()->addHour()->toAtomString(), 'scope' => 'webmasters.readonly', 'connected_at' => now()->toAtomString(),
    ])]);
    app(SeoSettings::class)->save(['gsc_property' => 'sc-domain:abrasilsistemas.com.br', 'ga4_property_id' => '123456']);
    Http::fake([
        'www.googleapis.com/webmasters/v3/sites' => Http::response(['siteEntry' => [['siteUrl' => 'sc-domain:abrasilsistemas.com.br', 'permissionLevel' => 'siteOwner']]]),
        'www.googleapis.com/webmasters/v3/sites/*/searchAnalytics/query' => Http::response(['rows' => [['keys' => ['sistemas sob medida'], 'clicks' => 12, 'impressions' => 340, 'ctr' => 0.035, 'position' => 8.24]]]),
        'analyticsdata.googleapis.com/*' => Http::response([], 403),
    ]);

    $this->actingAs(User::factory()->create())->get('/admin/seo/integracoes')->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('google.connected', true)
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('sites.data.0.siteUrl', 'sc-domain:abrasilsistemas.com.br')
                ->where('searchConsole.data.current.clicks', 12)
                ->where('searchConsole.data.queries.0.key', 'sistemas sob medida')
                ->where('searchConsole.data.queries.0.ctr', 3.5)
                ->where('analytics.data', null)
                ->where('analytics.error', fn ($error) => str_contains($error, 'não tem acesso'))));

    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer access'));
    expect(Setting::query()->where('key', Setting::SEO_GSC_TOKEN)->toBase()->value('value'))->not->toContain('refresh');
});
