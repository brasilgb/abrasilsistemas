<?php

use App\Models\Lead;
use App\Models\Setting;
use App\Models\User;
use App\Services\CompanyWhatsappSettings;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

const COMPANY_WHATSAPP_N8N_URL = 'https://n8n.test/webhook/crm/send-whatsapp';

beforeEach(function () {
    config([
        'services.n8n.whatsapp_webhook_url' => COMPANY_WHATSAPP_N8N_URL,
        'services.n8n.whatsapp_webhook_header' => 'X-CRM-Token',
        'services.n8n.whatsapp_webhook_token' => 'n8n-header-token',
    ]);

    Http::preventStrayRequests();
});

function companyWhatsappPayload(array $overrides = []): array
{
    return [
        'enabled' => true,
        'number' => '(51) 99893-1325',
        'provider' => 'waha',
        'session' => 'vetoros1-1',
        ...$overrides,
    ];
}

function saveCompanyWhatsapp(array $overrides = []): void
{
    app(CompanyWhatsappSettings::class)->save([
        'enabled' => true,
        'number' => '51998931325',
        'provider' => 'waha',
        'session' => 'sessao-teste',
        ...$overrides,
    ]);
}

function companyWhatsappLead(array $attributes = []): Lead
{
    return Lead::query()->create([
        'company_name' => 'Assistência Técnica ABC',
        'product' => 'vetoros',
        'status' => 'new',
        'whatsapp' => '51999998888',
        ...$attributes,
    ]);
}

test('admin saves the company WhatsApp configuration', function () {
    $this->actingAs(User::factory()->create())
        ->put(route('lead-settings.whatsapp.update'), companyWhatsappPayload())
        ->assertRedirect(route('lead-settings.edit'))
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast.type', 'success');

    expect(app(CompanyWhatsappSettings::class)->current())->toBe([
        'configured' => true,
        'enabled' => true,
        'number' => '5551998931325',
        'provider' => 'waha',
        'session' => 'vetoros1-1',
    ]);
});

test('company WhatsApp number is normalized to digits', function (string $typed, string $stored) {
    $this->actingAs(User::factory()->create())
        ->put(route('lead-settings.whatsapp.update'), companyWhatsappPayload(['number' => $typed]))
        ->assertSessionHasNoErrors();

    expect(app(CompanyWhatsappSettings::class)->current()['number'])->toBe($stored);
})->with([
    'com máscara' => ['(51) 99893-1325', '5551998931325'],
    'com +55' => ['+55 51 99893-1325', '5551998931325'],
    'só dígitos com 55' => ['5551998931325', '5551998931325'],
    'fixo' => ['51 3333-4444', '555133334444'],
]);

test('invalid company WhatsApp numbers are rejected', function (string $number) {
    $this->actingAs(User::factory()->create())
        ->put(route('lead-settings.whatsapp.update'), companyWhatsappPayload(['number' => $number]))
        ->assertSessionHasErrors('number');

    expect(app(CompanyWhatsappSettings::class)->current()['configured'])->toBeFalse();
})->with(['curto' => '12345', 'letras' => 'abc', 'longo demais' => '99 99999 99999 9999']);

test('only supported providers are accepted', function () {
    $this->actingAs(User::factory()->create())
        ->put(route('lead-settings.whatsapp.update'), companyWhatsappPayload(['provider' => 'evolution']))
        ->assertSessionHasErrors('provider');

    $this->actingAs(User::factory()->create())
        ->put(route('lead-settings.whatsapp.update'), companyWhatsappPayload(['provider' => 'waha']))
        ->assertSessionHasNoErrors();

    expect(app(CompanyWhatsappSettings::class)->current()['provider'])->toBe('waha');
});

test('the WAHA session is configurable and validated', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->put(route('lead-settings.whatsapp.update'), companyWhatsappPayload(['session' => 'outra_sessao-2']))
        ->assertSessionHasNoErrors();

    expect(app(CompanyWhatsappSettings::class)->current()['session'])->toBe('outra_sessao-2');

    $this->actingAs($user)
        ->put(route('lead-settings.whatsapp.update'), companyWhatsappPayload(['session' => 'sessão com espaço']))
        ->assertSessionHasErrors('session');

    expect(app(CompanyWhatsappSettings::class)->current()['session'])->toBe('outra_sessao-2');
});

test('number and session are required to enable the integration', function (string $field) {
    $this->actingAs(User::factory()->create())
        ->put(route('lead-settings.whatsapp.update'), companyWhatsappPayload([$field => '']))
        ->assertSessionHasErrors($field);
})->with(['number', 'session']);

test('the integration can be saved disabled without number and session', function () {
    $this->actingAs(User::factory()->create())
        ->put(route('lead-settings.whatsapp.update'), companyWhatsappPayload(['enabled' => false, 'number' => '', 'session' => '']))
        ->assertSessionHasNoErrors();

    expect(app(CompanyWhatsappSettings::class)->current())->toBe([
        'configured' => true,
        'enabled' => false,
        'number' => null,
        'provider' => 'waha',
        'session' => null,
    ]);
});

test('readers cannot change the company WhatsApp', function () {
    $this->actingAs(User::factory()->reader()->create())
        ->put(route('lead-settings.whatsapp.update'), companyWhatsappPayload())
        ->assertForbidden();

    expect(Setting::query()->count())->toBe(0);
});

test('settings page shows the company WhatsApp status without secrets', function () {
    $this->withoutVite();
    saveCompanyWhatsapp();

    $response = $this->actingAs(User::factory()->create())
        ->get(route('lead-settings.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/leads')
            ->where('companyWhatsapp', [
                'configured' => true,
                'enabled' => true,
                'number' => '5551998931325',
                'provider' => 'waha',
                'session' => 'sessao-teste',
            ])
            ->where('whatsappProviders', ['waha']));

    expect($response->getContent())
        ->not->toContain('n8n-header-token')
        ->not->toContain('n8n.test');
});

test('settings page reports the integration as not configured by default', function () {
    $this->withoutVite();

    $this->actingAs(User::factory()->create())
        ->get(route('lead-settings.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('companyWhatsapp.configured', false)
            ->where('companyWhatsapp.session', null));
});

test('sending uses the configured company session and sender number', function () {
    Http::fake([COMPANY_WHATSAPP_N8N_URL => Http::response(['success' => true, 'message_id' => 'X', 'status' => 'PENDING', 'activity_id' => 1])]);
    saveCompanyWhatsapp(['session' => 'sessao-configurada']);
    $lead = companyWhatsappLead();

    $this->actingAs(User::factory()->create())
        ->post(route('leads.whatsapp.store', $lead), ['message' => 'Olá'])
        ->assertSessionHasNoErrors();

    Http::assertSent(fn (Request $request) => $request->data() === [
        'prospect_id' => $lead->id,
        'nome' => 'Assistência Técnica ABC',
        'whatsapp' => '5551999998888',
        'mensagem' => 'Olá',
        'provider' => 'waha',
        'session' => 'sessao-configurada',
        'remetente_whatsapp' => '5551998931325',
    ]);
});

test('sending is blocked when the company integration is disabled', function () {
    Http::fake();
    saveCompanyWhatsapp(['enabled' => false]);
    $lead = companyWhatsappLead();

    $this->actingAs(User::factory()->create())
        ->post(route('leads.whatsapp.store', $lead), ['message' => 'Olá'])
        ->assertSessionHasErrors(['whatsapp' => 'O envio de WhatsApp está desativado nas configurações da empresa.']);

    Http::assertNothingSent();
});

test('without a saved configuration the existing n8n contract is kept', function () {
    Http::fake([COMPANY_WHATSAPP_N8N_URL => Http::response(['success' => true])]);
    $lead = companyWhatsappLead();

    $this->actingAs(User::factory()->create())
        ->post(route('leads.whatsapp.store', $lead), ['message' => 'Olá'])
        ->assertSessionHasNoErrors();

    Http::assertSent(fn (Request $request) => array_keys($request->data()) === ['prospect_id', 'nome', 'whatsapp', 'mensagem']);
});

test('lead WhatsApp and company WhatsApp do not interfere with each other', function () {
    saveCompanyWhatsapp(['number' => '51998931325']);
    $lead = companyWhatsappLead(['whatsapp' => '(51) 97777-6666']);

    $this->actingAs(User::factory()->create())
        ->put(route('leads.update', $lead), [
            'company_name' => $lead->company_name,
            'product' => 'vetoros',
            'status' => 'new',
            'whatsapp' => '(51) 95555-4444',
        ])
        ->assertSessionHasNoErrors();

    expect(app(CompanyWhatsappSettings::class)->current()['number'])->toBe('5551998931325');

    $this->actingAs(User::factory()->create())
        ->put(route('lead-settings.whatsapp.update'), companyWhatsappPayload(['number' => '51 91111-2222']))
        ->assertSessionHasNoErrors();

    expect($lead->fresh()->whatsapp)->toBe('51955554444')
        ->and(app(CompanyWhatsappSettings::class)->current()['number'])->toBe('5551911112222');
});
