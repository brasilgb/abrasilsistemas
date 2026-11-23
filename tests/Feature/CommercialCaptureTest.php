<?php

use App\Mail\ContactFormReceived;
use App\Models\BlogPost;
use App\Models\Lead;
use App\Models\User;
use App\Services\SiteRequestIntake;
use App\Services\VisitOrigin;
use App\Support\PublicPages;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

// ABRASIL-COMERCIAL-01: formulário comercial, origem da visita, consentimento e produtos.

beforeEach(fn () => Mail::fake());

function contactPayload(array $attributes = []): array
{
    return [
        'contact_name' => 'Ana',
        'email' => 'ana@exemplo.test',
        'whatsapp' => '(51) 98888-7777',
        'product' => 'site',
        'notes' => 'Precisamos de um site novo.',
        ...$attributes,
    ];
}

test('company is optional and the request is still listed and notified', function () {
    $this->post(route('contato.store'), contactPayload())->assertSessionHasNoErrors();

    $lead = Lead::query()->sole();
    expect($lead->company_name)->toBeNull()->and($lead->marketing_consent_at)->toBeNull();
    Mail::assertQueued(ContactFormReceived::class, fn ($mail) => str_contains($mail->envelope()->subject, 'Ana') && str_contains($mail->render(), 'Não identificada'));

    $admin = User::factory()->create();
    $this->actingAs($admin)->get(route('solicitacoes.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('requests.data.0.company_name', null));
    $this->actingAs($admin)->get(route('solicitacoes.show', $lead))->assertOk();
});

test('the commercial form is offered on the home, service and product pages', function (string $path) {
    $this->get($path)->assertOk()->assertInertia(fn (Assert $page) => $page->where('projectTypes', Lead::CONTACT_PROJECT_TYPES));
})->with(['/', '/automacao-de-processos', '/'.PublicPages::SEO_SERVICE, '/sistemas/vetorsmart']);

test('utm parameters and the referring site are saved with the request', function () {
    $this->get('/criacao-de-landing-pages?utm_source=google&utm_medium=cpc&utm_campaign=landing_nov', ['referer' => 'https://www.google.com/']);
    $this->get('/contato');
    $this->post(route('contato.store'), contactPayload(['marketing_consent' => '1']));

    $lead = Lead::query()->sole();
    expect($lead->only(VisitOrigin::FIELDS))->toBe([
        'entry_page' => '/criacao-de-landing-pages', 'referrer_host' => 'google.com', 'utm_source' => 'google',
        'utm_medium' => 'cpc', 'utm_campaign' => 'landing_nov', 'utm_term' => null, 'utm_content' => null,
    ])->and($lead->commercialOrigin())->toBe('Campanha: google / cpc / landing_nov')
        ->and($lead->marketing_consent_at)->not->toBeNull();

    $this->actingAs(User::factory()->create())->get(route('solicitacoes.show', $lead))
        ->assertInertia(fn (Assert $page) => $page->where('request.origin', 'Campanha: google / cpc / landing_nov')->where('request.utm.utm_campaign', 'landing_nov'));
});

test('the first campaign of the visit is kept and malformed values are discarded', function () {
    $this->get('/?utm_source=primeira');
    $this->get('/sobre?utm_source=segunda');
    expect(session(VisitOrigin::SESSION_KEY)['utm_source'])->toBe('primeira');

    $this->flushSession();
    $this->get('/?utm_source='.urlencode('<script>').'&utm_medium=cpc&utm_campaign='.urlencode('ana@exemplo.test'));
    expect(session(VisitOrigin::SESSION_KEY))->toBe(['entry_page' => '/', 'referrer_host' => null]);
});

test('the origin label never invents a source', function (array $origin, string $label) {
    expect(VisitOrigin::label($origin))->toBe($label);
})->with([
    [[], 'Não identificada'],
    [['referrer_host' => 'google.com.br'], 'Busca orgânica: Google'],
    [['referrer_host' => 'parceiro.com.br'], 'Indicação: parceiro.com.br'],
    [['referrer_host' => 'naogoogle.com'], 'Indicação: naogoogle.com'],
    [['utm_source' => 'instagram', 'referrer_host' => 'google.com'], 'Campanha: instagram'],
]);

test('resending the same message neither duplicates nor notifies again', function () {
    $this->post(route('contato.store'), contactPayload());
    $this->post(route('contato.store'), contactPayload())->assertSessionHasNoErrors();

    $lead = Lead::query()->sole();
    expect($lead->activities()->count())->toBe(0)->and($lead->notes)->toBe('Precisamos de um site novo.');
    Mail::assertQueuedCount(1);
});

test('a new message from the same person keeps the first origin and records the new interest', function () {
    $this->get('/?utm_source=instagram');
    $this->post(route('contato.store'), contactPayload());
    $this->flushSession();
    $this->get('/?utm_source=google');
    $this->post(route('contato.store'), contactPayload(['product' => 'seo', 'notes' => 'Agora quero SEO.']));

    $lead = Lead::query()->sole();
    expect($lead->utm_source)->toBe('instagram')->and($lead->product)->toBe('site')
        ->and($lead->activities()->sole()->description)->toContain('Campanha: google')->toContain('Serviço: SEO Profissional');
    Mail::assertQueued(ContactFormReceived::class, fn ($mail) => str_contains($mail->render(), 'Agora quero SEO.') && str_contains($mail->render(), 'SEO Profissional'));
    Mail::assertQueuedCount(2);

    // O reenvio do novo contato também não duplica.
    $this->post(route('contato.store'), contactPayload(['product' => 'seo', 'notes' => 'Agora quero SEO.']));
    expect($lead->activities()->count())->toBe(1);
    Mail::assertQueuedCount(2);
});

test('the first identified origin is kept whole', function () {
    // Busca orgânica primeiro: a campanha posterior não se mistura com ela.
    $this->get('/sobre', ['referer' => 'https://www.google.com/']);
    $this->get('/?utm_source=instagram');
    expect(session(VisitOrigin::SESSION_KEY))->toBe(['entry_page' => '/sobre', 'referrer_host' => 'google.com']);

    // Entrada direta: vale a primeira origem identificada depois dela.
    $this->flushSession();
    $this->get('/servicos');
    $this->get('/contato', ['referer' => 'https://parceiro.com.br/x']);
    $this->get('/?utm_source=instagram');
    expect(session(VisitOrigin::SESSION_KEY))->toBe(['entry_page' => '/servicos', 'referrer_host' => 'parceiro.com.br']);
});

test('an expired session returns a message in the form', function () {
    Route::post('contato', fn () => throw new TokenMismatchException)->middleware('web');

    $this->from('/contato')->post('/contato', contactPayload())->assertRedirect('/contato')->assertSessionHasErrors('form');
});

test('spam and invalid data are refused without saving', function (array $data, string $field) {
    $this->post(route('contato.store'), contactPayload($data))->assertSessionHasErrors($field);
    expect(Lead::query()->count())->toBe(0);
    Mail::assertNothingQueued();
})->with([
    'honeypot' => [['website_hp' => 'x'], 'website_hp'],
    'links' => [['notes' => str_repeat('veja https://spam.test ', 4)], 'notes'],
    'link no nome' => [['contact_name' => 'Ganhe em https://spam.test'], 'contact_name'],
    'telefone curto' => [['whatsapp' => '1234'], 'whatsapp'],
    'sem contato' => [['email' => '', 'whatsapp' => ''], 'email'],
]);

test('a failure to save returns an error instead of a confirmation', function () {
    $this->mock(SiteRequestIntake::class)->shouldReceive('store')->andThrow(new RuntimeException('banco fora'));

    $this->post(route('contato.store'), contactPayload())->assertRedirect()->assertSessionHasErrors('form')->assertSessionMissing('inertia.flash_data');
    Mail::assertNothingQueued();
});

test('too many submissions return a message in the form', function () {
    foreach (range(1, 5) as $i) {
        $this->post(route('contato.store'), contactPayload(['email' => "a{$i}@exemplo.test", 'whatsapp' => "5198888000{$i}"]));
    }

    $this->post(route('contato.store'), contactPayload(['email' => 'z@exemplo.test', 'whatsapp' => '']))->assertRedirect()->assertSessionHasErrors('form');
    expect(Lead::query()->count())->toBe(5);
});

test('VetorSmart is presented as a demonstration, with its pending features apart', function () {
    $site = file_get_contents(resource_path('js/data/site.ts'));

    expect(preg_match("/slug: 'vetorsmart',\n        status: 'demo',/", $site))->toBe(1)
        ->and(Lead::CONTACT_PROJECT_TYPES)->toHaveKey('vetorsmart');
    $this->get('/sitemap.xml')->assertSee(url('/sistemas/vetorsmart'), false);
});

test('the hero image is preloaded on pages that have one', function () {
    $this->get('/')->assertSee('<link rel="preload" as="image" type="image/avif"', false)->assertSee('/images/site/heroes/home-1920.avif 1920w', false);
    $this->get('/automacao-de-processos')->assertSee('/images/site/heroes/automacao-640.avif 640w', false);
    $this->get('/login')->assertDontSee('rel="preload" as="image"', false);
});

test('the commercial drafts are created unpublished in their own categories', function () {
    User::factory()->create();
    $this->artisan('blog:seo-drafts')->assertSuccessful();

    $post = BlogPost::query()->where('slug', 'quanto-custa-um-sistema-sob-medida')->sole();
    expect($post->status)->toBe('draft')->and($post->category->slug)->toBe('sistemas-e-automacao');
});
