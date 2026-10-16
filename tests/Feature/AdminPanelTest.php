<?php

use App\Models\Lead;
use App\Models\User;

/*
 * Painel do site institucional (ABRASIL-WEB-03): atendimento + solicitações + conteúdo.
 * Prospecção e CRM saíram do site e ficaram no VetorLeads.
 */

test('guests are redirected to the login page', function (string $uri) {
    $this->get($uri)->assertRedirect(route('login'));
})->with([
    '/atendimento',
    '/solicitacoes',
    '/settings/whatsapp',
    '/users',
]);

test('users without admin permission cannot open the panel areas', function () {
    $reader = User::factory()->reader()->create();
    $request = Lead::query()->create(['company_name' => 'Empresa', 'product' => 'site', 'source' => 'site', 'request_status' => 'new']);

    $this->actingAs($reader)->get(route('atendimento.index'))->assertForbidden();
    $this->actingAs($reader)->get(route('solicitacoes.index'))->assertForbidden();
    $this->actingAs($reader)->get(route('solicitacoes.show', $request))->assertForbidden();
    $this->actingAs($reader)->patch(route('solicitacoes.status', $request), ['request_status' => 'done'])->assertForbidden();
    $this->actingAs($reader)->get(route('whatsapp-settings.edit'))->assertForbidden();

    expect($request->fresh()->request_status)->toBe('new');
});

test('the admin shortcut and the restricted area open the inbox', function () {
    $admin = User::factory()->create();

    $this->actingAs($admin)->get('/admin')->assertRedirect('/atendimento');
    $this->actingAs($admin)->get(route('restricted-area'))->assertRedirect(route('atendimento.index'));
    $this->actingAs($admin)->get(route('atendimento.index'))->assertOk();
});

test('the commercial CRM is no longer reachable from the institutional site', function (string $method, string $uri) {
    $admin = User::factory()->create();
    $lead = Lead::query()->create(['company_name' => 'Prospect', 'product' => 'vetoros', 'source' => 'Google Maps']);

    $this->actingAs($admin)
        ->call($method, str_replace('{lead}', (string) $lead->id, $uri))
        ->assertNotFound();

    // Nada é apagado: o prospect continua no banco.
    expect(Lead::query()->whereKey($lead->id)->exists())->toBeTrue();
})->with([
    'dashboard comercial' => ['GET', '/dashboard'],
    'lista/Kanban/funil' => ['GET', '/leads'],
    'novo lead' => ['GET', '/leads/create'],
    'criar lead' => ['POST', '/leads'],
    'editar lead' => ['GET', '/leads/{lead}/edit'],
    'atualizar lead' => ['PUT', '/leads/{lead}'],
    'excluir lead' => ['DELETE', '/leads/{lead}'],
    'status pelo Kanban' => ['PATCH', '/leads/{lead}/status'],
    'ações em massa' => ['PATCH', '/leads/bulk'],
    'importação CSV' => ['POST', '/leads/import'],
    'apagar todos' => ['DELETE', '/leads/clear'],
    'atividades/follow-up' => ['POST', '/leads/{lead}/activities'],
    'envio de WhatsApp via n8n' => ['POST', '/leads/{lead}/whatsapp'],
    'configurações de leads' => ['GET', '/settings/leads'],
    'token da extensão' => ['PUT', '/settings/leads'],
    'download da extensão' => ['GET', '/files/ab-prospect-firefox-v1.3.0.xpi'],
]);

test('the extension package is no longer distributed by the site', function () {
    expect(glob(public_path('files/ab-prospect-*')))->toBe([])
        ->and(is_dir(base_path('extension')))->toBeFalse();
});

test('legacy integration endpoints stay protected until they move to VetorLeads', function () {
    // Mantidas de propósito (extensão AB Prospect e n8n ainda podem apontar para o site): sem token, 401.
    $this->postJson(route('api.prospects.import'), [])->assertUnauthorized();
    $this->postJson('/api/prospects/1/whatsapp/log', [])->assertUnauthorized();
    $this->patchJson('/api/whatsapp/messages/ABC/status', [])->assertUnauthorized();
});
