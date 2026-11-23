<?php

use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\User;
use App\Models\WhatsappConversation;
use App\Services\CompanyWhatsappSettings;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Área "Solicitações" (ABRASIL-WEB-03): só os contatos do formulário do site (source = site),
 * do novo contato ao ganho ou perda (ABRASIL-COMERCIAL-02). Prospects antigos continuam no banco, mas não aparecem aqui.
 */

function siteRequest(array $attributes = []): Lead
{
    return Lead::query()->create([
        'company_name' => 'Empresa do Site',
        'contact_name' => 'Maria',
        'product' => 'automacao',
        'source' => 'site',
        'request_status' => 'new',
        'notes' => 'Quero automatizar a cobrança.',
        ...$attributes,
    ]);
}

function commercialProspect(array $attributes = []): Lead
{
    return Lead::query()->create([
        'company_name' => 'Prospect do Maps',
        'product' => 'vetoros',
        'source' => 'Google Maps',
        'status' => 'contacted',
        ...$attributes,
    ]);
}

beforeEach(function () {
    Queue::fake();
});

test('only requests sent by the site form are listed', function () {
    $request = siteRequest(['email' => 'maria@empresa.test', 'source_page' => '/automacao-de-processos']);
    commercialProspect();
    commercialProspect(['company_name' => 'Importado por CSV', 'source' => 'CSV']);
    commercialProspect(['company_name' => 'Sem origem', 'source' => null]);

    $this->actingAs(User::factory()->create())
        ->get(route('solicitacoes.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('solicitacoes/index')
            ->has('requests.data', 1)
            ->where('requests.data.0.id', $request->id)
            ->where('requests.data.0.project_type', 'Automação')
            ->where('requests.data.0.source_page', '/automacao-de-processos')
            ->where('requests.data.0.request_status', 'new')
            ->where('counts.todas', 1)
            ->where('statuses', Lead::REQUEST_STATUSES));
});

test('the list shows open requests by default and filters by status', function () {
    siteRequest();
    siteRequest(['request_status' => 'in_progress', 'email' => 'b@x.test']);
    siteRequest(['request_status' => 'won', 'email' => 'c@x.test']);
    $archived = siteRequest(['request_status' => 'archived', 'email' => 'd@x.test']);
    $admin = User::factory()->create();

    $this->actingAs($admin)->get(route('solicitacoes.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.situacao', 'abertas')
            ->has('requests.data', 2)
            ->where('counts.abertas', 2)
            ->where('counts.won', 1)
            ->where('counts.archived', 1));

    $this->actingAs($admin)->get(route('solicitacoes.index', ['situacao' => 'archived']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('requests.data', 1)
            ->where('requests.data.0.id', $archived->id));

    $this->actingAs($admin)->get(route('solicitacoes.index', ['situacao' => 'todas']))
        ->assertInertia(fn (Assert $page) => $page->has('requests.data', 4));

    $this->actingAs($admin)->get(route('solicitacoes.index', ['situacao' => 'invalida']))
        ->assertInertia(fn (Assert $page) => $page->where('filters.situacao', 'abertas'));
});

test('requests can be searched by name, company, e-mail or phone', function () {
    siteRequest(['contact_name' => 'Joana Lima', 'company_name' => 'Oficina Central', 'whatsapp' => '51977776666']);
    siteRequest(['contact_name' => 'Pedro', 'company_name' => 'Padaria', 'email' => 'pedro@padaria.test']);
    $admin = User::factory()->create();

    foreach (['Joana', 'Oficina', '(51) 97777-6666', 'pedro@padaria'] as $term) {
        $this->actingAs($admin)->get(route('solicitacoes.index', ['busca' => $term]))
            ->assertInertia(fn (Assert $page) => $page->has('requests.data', 1));
    }
});

test('a request shows its data, history and linked WhatsApp conversation', function () {
    $request = siteRequest(['whatsapp' => '51988887777', 'source_page' => '/sistemas/vetoros']);
    $request->activities()->create(['type' => 'note', 'description' => 'Novo contato recebido pelo formulário do site: segunda mensagem']);
    $conversation = WhatsappConversation::query()->create([
        'session' => 'abrasil-atendimento',
        'chat_id' => '5551988887777@c.us',
        'phone' => '5551988887777',
        'lead_id' => $request->id,
        'last_message_preview' => 'Olá!',
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('solicitacoes.show', $request))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('solicitacoes/show')
            ->where('request.id', $request->id)
            ->where('request.message', 'Quero automatizar a cobrança.')
            ->where('request.source_page', '/sistemas/vetoros')
            ->where('request.project_type', 'Automação')
            ->has('history', 1)
            ->has('conversations', 1)
            ->where('conversations.0.id', $conversation->id));
});

test('a commercial prospect cannot be opened or changed as a request', function () {
    $prospect = commercialProspect();
    $admin = User::factory()->create();

    $this->actingAs($admin)->get(route('solicitacoes.show', $prospect))->assertNotFound();
    $this->actingAs($admin)->patch(route('solicitacoes.status', $prospect), ['request_status' => 'done'])->assertNotFound();

    expect($prospect->fresh()->request_status)->toBeNull()
        ->and($prospect->fresh()->status)->toBe('contacted');
});

test('the request status can be changed, including archiving', function (string $status) {
    $request = siteRequest();

    $this->actingAs(User::factory()->create())
        ->from(route('solicitacoes.show', $request))
        ->patch(route('solicitacoes.status', $request), ['request_status' => $status])
        ->assertRedirect(route('solicitacoes.show', $request))
        ->assertSessionHasNoErrors();

    expect($request->fresh()->request_status)->toBe($status);
})->with(['in_progress', 'proposal', 'negotiation', 'won', 'lost', 'archived']);

test('an unknown request status is rejected', function () {
    $request = siteRequest(['request_status' => 'in_progress']);

    $this->actingAs(User::factory()->create())
        ->patch(route('solicitacoes.status', $request), ['request_status' => 'answered'])
        ->assertSessionHasErrors('request_status');

    expect($request->fresh()->request_status)->toBe('in_progress');
});

test('changing the status keeps the earlier history and the legacy commercial data', function () {
    $request = siteRequest(['status' => 'interested', 'notes' => 'Histórico antigo']);
    $request->activities()->create(['type' => 'whatsapp', 'description' => 'Mensagem enviada no tempo do CRM']);

    $this->actingAs(User::factory()->create())
        ->patch(route('solicitacoes.status', $request), ['request_status' => 'archived']);

    $request->refresh();

    expect($request->status)->toBe('interested')
        ->and($request->notes)->toBe('Histórico antigo')
        ->and($request->activities()->where('type', 'whatsapp')->count())->toBe(1)
        ->and(Lead::withTrashed()->count())->toBe(1);
});

test('the contact form creates a new request with its origin page', function () {
    $this->post(route('contato.store'), [
        'contact_name' => 'Carlos',
        'company_name' => 'Transportes Sul',
        'whatsapp' => '(51) 96666-5555',
        'product' => 'integracao',
        'notes' => 'Integrar o ERP com a loja virtual.',
        'source_page' => '/integracao-de-sistemas-e-apis',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $request = Lead::query()->sole();

    expect($request->source)->toBe('site')
        ->and($request->request_status)->toBe('new')
        ->and($request->source_page)->toBe('/integracao-de-sistemas-e-apis')
        ->and($request->whatsapp)->toBe('51966665555');
});

test('an invalid origin page is discarded without refusing the contact', function (string $page) {
    $this->post(route('contato.store'), [
        'contact_name' => 'Carlos',
        'company_name' => 'Transportes Sul',
        'email' => 'carlos@sul.test',
        'product' => 'site',
        'notes' => 'Quero um site.',
        'source_page' => $page,
    ])->assertSessionHasNoErrors();

    expect(Lead::query()->sole()->source_page)->toBeNull();
})->with(['https://outro-site.test/pagina', '//outro-site.test', 'sem-barra', '/com espaço']);

test('a repeated contact enters the open request without changing its data or stage', function () {
    $request = siteRequest(['email' => 'contato@empresa.test', 'request_status' => 'negotiation', 'notes' => 'Primeiro contato.', 'utm_source' => 'instagram']);

    $this->post(route('contato.store'), [
        'contact_name' => 'Outro nome',
        'company_name' => 'Outra empresa',
        'email' => 'CONTATO@EMPRESA.TEST',
        'product' => 'site',
        'notes' => 'Solicitou uma nova apresentação.',
        'source_page' => '/contato',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $request->refresh();

    expect(Lead::query()->count())->toBe(1)
        ->and($request->only(['contact_name', 'company_name', 'product', 'notes', 'utm_source', 'source_page', 'request_status', 'whatsapp']))->toBe([
            'contact_name' => 'Maria', 'company_name' => 'Empresa do Site', 'product' => 'automacao', 'notes' => 'Primeiro contato.',
            'utm_source' => 'instagram', 'source_page' => null, 'request_status' => 'negotiation', 'whatsapp' => null,
        ])
        ->and($request->last_contacted_at)->not->toBeNull()
        ->and($request->activities()->sole()->description)->toContain('Nome informado: Outro nome')->toContain('Solicitou uma nova apresentação.');
});

test('a contact after the request was closed opens a related one and keeps the result', function (string $status) {
    $closed = siteRequest(['email' => 'contato@empresa.test', 'request_status' => $status]);

    $this->post(route('contato.store'), ['contact_name' => 'Maria', 'email' => 'contato@empresa.test', 'product' => 'site', 'notes' => 'Novo projeto.']);

    $new = Lead::query()->latest('id')->first();
    expect($new->id)->not->toBe($closed->id)->and($new->request_status)->toBe('new')
        ->and($closed->fresh()->request_status)->toBe($status);

    $this->actingAs(User::factory()->create())->get(route('solicitacoes.show', $new))
        ->assertInertia(fn (Assert $page) => $page->has('related', 1)->where('related.0.id', $closed->id));
})->with(['won', 'lost', 'archived']);

test('someone using another person e-mail or phone never changes that person data', function (array $contact) {
    $victim = siteRequest(['email' => 'vitima@empresa.test', 'whatsapp' => '51988887777', 'request_status' => 'proposal']);
    $before = $victim->fresh()->getAttributes();

    $response = $this->post(route('contato.store'), ['contact_name' => 'Terceiro', 'product' => 'site', 'notes' => 'Mensagem de terceiro.', ...$contact]);
    $response->assertRedirect()->assertSessionHasNoErrors();

    expect($victim->fresh()->getAttributes())->toBe($before)
        ->and($victim->activities()->count())->toBe(0)
        ->and(Lead::query()->count())->toBe(2);
})->with([
    'e-mail da vítima, outro telefone' => [['email' => 'vitima@empresa.test', 'whatsapp' => '51911112222']],
    'telefone da vítima, outro e-mail' => [['email' => 'terceiro@x.test', 'whatsapp' => '51988887777']],
]);

test('the answer to the visitor is the same for new and repeated contacts', function () {
    siteRequest(['email' => 'conhecido@empresa.test']);
    $send = fn (string $email) => $this->post(route('contato.store'), ['contact_name' => 'X', 'email' => $email, 'product' => 'site', 'notes' => 'Olá.']);

    $known = $send('conhecido@empresa.test');
    $flash = session('inertia.flash_data');
    $new = $send('desconhecido@empresa.test');

    expect($known->getStatusCode())->toBe($new->getStatusCode())
        ->and($known->headers->get('Location'))->toBe($new->headers->get('Location'))
        ->and($flash)->toBe(session('inertia.flash_data'));
});

test('status changes and notes are recorded with their author', function () {
    $request = siteRequest();
    $admin = User::factory()->create(['name' => 'Anderson']);

    $this->actingAs($admin)->patch(route('solicitacoes.status', $request), ['request_status' => 'proposal', 'note' => 'Enviada por e-mail']);
    $this->actingAs($admin)->patch(route('solicitacoes.status', $request), ['request_status' => 'proposal']);
    $this->actingAs($admin)->patch(route('solicitacoes.status', $request), ['request_status' => 'lost', 'note' => 'Sem orçamento']);
    $this->actingAs($admin)->post(route('solicitacoes.notes', $request), ['description' => 'Retomar em março.'])->assertSessionHasNoErrors();
    $this->actingAs($admin)->post(route('solicitacoes.notes', $request), ['description' => ''])->assertSessionHasErrors('description');

    expect($request->fresh()->lost_reason)->toBe('Sem orçamento')
        ->and($request->activities()->oldest('id')->pluck('description')->all())->toBe([
            'Novo contato → Proposta enviada'.PHP_EOL.'Enviada por e-mail', 'Proposta enviada → Perdido'.PHP_EOL.'Sem orçamento', 'Retomar em março.',
        ]);

    $this->actingAs($admin)->get(route('solicitacoes.show', $request))
        ->assertInertia(fn (Assert $page) => $page->has('history', 3)->where('history.0.author', 'Anderson')->where('history.0.type', 'Nota')->where('request.lost_reason', 'Sem orçamento'));
});

test('requests are protected from visitors, readers and prospects', function () {
    $request = siteRequest();
    $routes = [['get', 'solicitacoes.index'], ['get', 'solicitacoes.show'], ['patch', 'solicitacoes.status'], ['post', 'solicitacoes.notes']];

    foreach ($routes as [$method, $name]) {
        $this->{$method}(route($name, $request))->assertRedirect(route('login'));
    }

    $this->actingAs(User::factory()->reader()->create());
    foreach ($routes as [$method, $name]) {
        $this->{$method}(route($name, $request))->assertForbidden();
    }

    $this->actingAs(User::factory()->create())->post(route('solicitacoes.notes', commercialProspect()), ['description' => 'x'])->assertNotFound();
    expect(LeadActivity::query()->count())->toBe(0);
});

test('the list is filtered by service, origin and date, and the counters follow', function (array $filter, array $expected) {
    $make = fn (string $name, array $attributes) => siteRequest(['contact_name' => $name, ...$attributes]);
    $make('campanha', ['product' => 'site', 'utm_source' => 'instagram', 'referrer_host' => 'google.com']);
    $make('busca', ['product' => 'seo', 'referrer_host' => 'google.com.br', 'request_status' => 'won']);
    $make('indicacao', ['referrer_host' => 'naogoogle.com']);
    // 23h30 de 31/10 em Brasília já é 01/11 em UTC.
    $make('direta', [])->forceFill(['created_at' => '2026-11-01 02:30:00'])->save();

    $this->actingAs(User::factory()->create())->get(route('solicitacoes.index', ['situacao' => 'todas', ...$filter]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('requests.data', fn ($data) => collect($data)->pluck('contact_name')->sort()->values()->all() === $expected)
            ->where('counts.todas', count($expected)));
})->with([
    [['servico' => 'site'], ['campanha']],
    [['servico' => 'inexistente'], ['busca', 'campanha', 'direta', 'indicacao']],
    [['origem' => 'campanha'], ['campanha']],
    [['origem' => 'utm:instagram'], ['campanha']],
    [['origem' => 'busca'], ['busca']],
    [['origem' => 'indicacao'], ['indicacao']],
    [['origem' => 'nao-identificada'], ['direta']],
    [['de' => '2026-10-31', 'ate' => '2026-10-31'], ['direta']],
    [['de' => '2026-13-99'], ['busca', 'campanha', 'direta', 'indicacao']],
]);

test('the old request statuses are migrated without presuming sales and can be rolled back', function () {
    $migration = require database_path('migrations/2026_11_23_180000_adopt_commercial_request_statuses.php');
    $answered = siteRequest(['request_status' => 'answered']);
    $done = siteRequest(['request_status' => 'done']);
    $prospect = commercialProspect(['request_status' => 'done']);

    $migration->up();
    expect($answered->fresh()->request_status)->toBe('in_progress')
        ->and($done->fresh()->request_status)->toBe('archived')
        ->and($done->activities()->sole()->description)->toContain('Concluída → Arquivada')
        ->and($prospect->fresh()->request_status)->toBe('done');

    $migration->down();
    expect($answered->fresh()->request_status)->toBe('answered')
        ->and($done->fresh()->request_status)->toBe('done')
        ->and(LeadActivity::query()->count())->toBe(0);
});

test('a site contact is never merged into a commercial prospect with the same e-mail', function () {
    $prospect = commercialProspect(['email' => 'contato@empresa.test', 'notes' => 'Anotação comercial']);

    $this->post(route('contato.store'), [
        'contact_name' => 'Maria',
        'company_name' => 'Empresa do Site',
        'email' => 'contato@empresa.test',
        'product' => 'site',
        'notes' => 'Quero um orçamento.',
    ])->assertRedirect();

    expect(Lead::query()->count())->toBe(2)
        ->and($prospect->fresh()->notes)->toBe('Anotação comercial')
        ->and(Lead::query()->siteRequests()->sole()->notes)->toBe('Quero um orçamento.');
});

test('a new request is linked to an earlier WhatsApp conversation from the same number', function () {
    app(CompanyWhatsappSettings::class)->save([
        'enabled' => true,
        'number' => '51998931325',
        'provider' => 'waha',
        'session' => 'abrasil-atendimento',
    ]);
    $prospect = commercialProspect(['whatsapp' => '51955554444']);
    $sameNumber = WhatsappConversation::query()->create(['session' => 'abrasil-atendimento', 'chat_id' => '5551955554444@c.us', 'phone' => '5551955554444', 'lead_id' => $prospect->id]);
    $otherSession = WhatsappConversation::query()->create(['session' => 'sessao-comercial', 'chat_id' => '5551955554444@c.us', 'phone' => '5551955554444']);

    $this->post(route('contato.store'), [
        'contact_name' => 'Ana',
        'company_name' => 'Clínica Ana',
        'whatsapp' => '(51) 95555-4444',
        'product' => 'sistema_sob_medida',
        'notes' => 'Preciso de um sistema de agenda.',
    ])->assertRedirect();

    $request = Lead::query()->siteRequests()->sole();

    expect($sameNumber->fresh()->lead_id)->toBe($request->id)
        ->and($otherSession->fresh()->lead_id)->toBeNull();
});
