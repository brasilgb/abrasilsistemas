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
 * sem funil comercial. Prospects antigos continuam no banco, mas não aparecem aqui.
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
    siteRequest(['request_status' => 'done', 'email' => 'c@x.test']);
    $archived = siteRequest(['request_status' => 'archived', 'email' => 'd@x.test']);
    $admin = User::factory()->create();

    $this->actingAs($admin)->get(route('solicitacoes.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.situacao', 'abertas')
            ->has('requests.data', 2)
            ->where('counts.abertas', 2)
            ->where('counts.done', 1)
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
})->with(['new', 'in_progress', 'answered', 'done', 'archived']);

test('an unknown request status is rejected', function () {
    $request = siteRequest(['request_status' => 'in_progress']);

    $this->actingAs(User::factory()->create())
        ->patch(route('solicitacoes.status', $request), ['request_status' => 'converted'])
        ->assertSessionHasErrors('request_status');

    expect($request->fresh()->request_status)->toBe('in_progress');
});

test('changing the status never touches the history or the legacy commercial data', function () {
    $request = siteRequest(['status' => 'interested', 'notes' => 'Histórico antigo']);
    $request->activities()->create(['type' => 'whatsapp', 'description' => 'Mensagem enviada no tempo do CRM']);

    $this->actingAs(User::factory()->create())
        ->patch(route('solicitacoes.status', $request), ['request_status' => 'archived']);

    $request->refresh();

    expect($request->status)->toBe('interested')
        ->and($request->notes)->toBe('Histórico antigo')
        ->and($request->activities()->count())->toBe(1)
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

test('a repeated contact reopens the same request and keeps its history', function () {
    $request = siteRequest(['email' => 'contato@empresa.test', 'request_status' => 'done', 'notes' => 'Primeiro contato.']);

    $this->post(route('contato.store'), [
        'contact_name' => 'Maria',
        'company_name' => 'Empresa do Site',
        'email' => 'CONTATO@EMPRESA.TEST',
        'product' => 'site',
        'notes' => 'Solicitou uma nova apresentação.',
    ])->assertRedirect();

    $request->refresh();

    expect(Lead::query()->count())->toBe(1)
        ->and($request->request_status)->toBe('new')
        ->and($request->notes)->toContain('Primeiro contato.')
        ->and($request->notes)->toContain('Solicitou uma nova apresentação.')
        ->and($request->last_contacted_at)->not->toBeNull()
        ->and(LeadActivity::query()->where('lead_id', $request->id)->count())->toBe(1);
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
