<?php

use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\User;
use App\Services\Seo\PageRegistry;
use App\Services\Seo\SeoSettings;
use Illuminate\Support\Facades\File;

// ABRASIL-COMERCIAL-02 §4: conteúdo das páginas públicas no HTML inicial, sem Node em execução.

beforeEach(function () {
    $this->path = sys_get_temp_dir().'/ab-prerender-'.uniqid();
    File::ensureDirectoryExists($this->path);
    config(['seo.prerender' => true, 'seo.prerender_path' => $this->path]);
    $this->store = fn (string $name, string $component, string $html) => File::put("{$this->path}/{$name}.json", json_encode(compact('component', 'html')));
});

afterEach(fn () => File::deleteDirectory($this->path));

test('the prerendered content is served inside the app root with the current company data', function () {
    ($this->store)('sobre', 'sobre', '<h1>Sobre</h1><a href="mailto:__AB_EMAIL__">__AB_EMAIL__</a><p>__AB_COMPANY__</p><i>__AB_WHATSAPP_DISPLAY__</i>');
    app(SeoSettings::class)->save(['organization_legal_name' => 'Empresa <b>Teste</b>', 'organization_email' => 'oi@abrasil.test']);

    $this->get('/sobre?utm_source=x')->assertOk()
        ->assertSee('<div id="app"><h1>Sobre</h1><a href="mailto:oi@abrasil.test">', false)
        ->assertSee('Empresa &lt;b&gt;Teste&lt;/b&gt; · Atendimento: Brasil', false)
        ->assertSee(config('contact.whatsapp_display'))
        ->assertSee('<script data-page="app" type="application/json">', false)
        ->assertDontSee('data-server-rendered', false);
});

test('without a matching file the page works as before', function () {
    ($this->store)('contato', 'sobre', '<h1>Outra tela</h1>');

    foreach (['/contato', '/servicos'] as $path) {
        $this->get($path)->assertOk()->assertSee('<div id="app"></div>', false)->assertDontSee('Outra tela');
    }
});

test('logged users, private screens and the kill switch skip the prerendered content', function () {
    ($this->store)('sobre', 'sobre', '<h1>Sobre</h1>');
    ($this->store)('login', 'login', '<h1>Login</h1>');

    $this->get('/login')->assertSee('<div id="app"></div>', false);
    config(['seo.prerender' => false]);
    $this->get('/sobre')->assertSee('<div id="app"></div>', false);
    config(['seo.prerender' => true]);
    $this->actingAs(User::factory()->create())->get('/sobre')->assertSee('<div id="app"></div>', false);
});

test('blog articles and listing come in the initial html, sanitized', function () {
    ($this->store)('blog', 'blog-shell', '<header></header>__AB_CONTENT__<footer></footer>');
    $category = BlogCategory::query()->first();
    $make = fn (string $slug, string $body) => BlogPost::query()->create(['user_id' => User::factory()->create()->id, 'blog_category_id' => $category->id, 'title' => "Artigo {$slug}", 'slug' => $slug, 'excerpt' => 'Resumo do artigo', 'body' => $body, 'status' => 'published', 'published_at' => now()->subDay()]);
    $make('novo', '<h2>Seção</h2><p>Texto __AB_EMAIL__</p><script>alert(1)</script><img src="x" onerror="alert(2)">');
    $make('antigo', "## Título antigo\n\nParágrafo **forte** <script>alert(3)</script>");

    $this->get('/blog')->assertOk()->assertSee('<a href="/blog/novo">Artigo novo</a>', false);
    $this->get('/blog/antigo')->assertOk()->assertSee('<h2>Título antigo</h2>', false)->assertSee('<strong>forte</strong>', false)->assertDontSee('<script>alert(3)', false);

    $html = str($this->get('/blog/novo')->assertOk()->getContent())->after('<div id="app">')->toString();
    expect($html)->toContain('Artigo novo</h1>', '<h2>Seção</h2>', 'Texto __AB_EMAIL__')->not->toContain('alert(1)', 'onerror');
});

test('every public page of the build has content, one h1 and no leftover markers', function () {
    $files = File::glob(public_path('build/prerender').'/*.json');
    expect(count($files))->toBeGreaterThanOrEqual(20);

    config(['seo.prerender_path' => public_path('build/prerender')]);
    foreach (app(PageRegistry::class)->all() as $entry) {
        if (! str_starts_with($entry['key'] ?? '', 'page:')) {
            continue;
        }
        $html = str($this->get($entry['path'])->assertOk()->getContent())->after('<div id="app">')->before('</body>')->toString();
        expect(substr_count($html, '<h1'))->toBe(1, $entry['path'])->and($html)->toContain('<footer')->not->toContain('__AB_');
    }
})->skip(fn () => ! is_dir(public_path('build/prerender')), 'Rode npm run build antes.');
