<?php

use App\Models\Setting;
use App\Models\User;
use App\Services\CompanyWhatsappSettings;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
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

test('admin saves the company WhatsApp configuration', function () {
    $this->actingAs(User::factory()->create())
        ->put(route('whatsapp-settings.update'), companyWhatsappPayload())
        ->assertRedirect(route('whatsapp-settings.edit'))
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
        ->put(route('whatsapp-settings.update'), companyWhatsappPayload(['number' => $typed]))
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
        ->put(route('whatsapp-settings.update'), companyWhatsappPayload(['number' => $number]))
        ->assertSessionHasErrors('number');

    expect(app(CompanyWhatsappSettings::class)->current()['configured'])->toBeFalse();
})->with(['curto' => '12345', 'letras' => 'abc', 'longo demais' => '99 99999 99999 9999']);

test('only supported providers are accepted', function () {
    $this->actingAs(User::factory()->create())
        ->put(route('whatsapp-settings.update'), companyWhatsappPayload(['provider' => 'evolution']))
        ->assertSessionHasErrors('provider');

    $this->actingAs(User::factory()->create())
        ->put(route('whatsapp-settings.update'), companyWhatsappPayload(['provider' => 'waha']))
        ->assertSessionHasNoErrors();

    expect(app(CompanyWhatsappSettings::class)->current()['provider'])->toBe('waha');
});

test('the WAHA session is configurable and validated', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->put(route('whatsapp-settings.update'), companyWhatsappPayload(['session' => 'outra_sessao-2']))
        ->assertSessionHasNoErrors();

    expect(app(CompanyWhatsappSettings::class)->current()['session'])->toBe('outra_sessao-2');

    $this->actingAs($user)
        ->put(route('whatsapp-settings.update'), companyWhatsappPayload(['session' => 'sessão com espaço']))
        ->assertSessionHasErrors('session');

    expect(app(CompanyWhatsappSettings::class)->current()['session'])->toBe('outra_sessao-2');
});

test('number and session are required to enable the integration', function (string $field) {
    $this->actingAs(User::factory()->create())
        ->put(route('whatsapp-settings.update'), companyWhatsappPayload([$field => '']))
        ->assertSessionHasErrors($field);
})->with(['number', 'session']);

test('the integration can be saved disabled without number and session', function () {
    $this->actingAs(User::factory()->create())
        ->put(route('whatsapp-settings.update'), companyWhatsappPayload(['enabled' => false, 'number' => '', 'session' => '']))
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
        ->put(route('whatsapp-settings.update'), companyWhatsappPayload())
        ->assertForbidden();

    expect(Setting::query()->count())->toBe(0);
});

test('settings page shows the company WhatsApp status without secrets', function () {
    $this->withoutVite();
    saveCompanyWhatsapp();

    $response = $this->actingAs(User::factory()->create())
        ->get(route('whatsapp-settings.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/whatsapp')
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
        ->get(route('whatsapp-settings.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('companyWhatsapp.configured', false)
            ->where('companyWhatsapp.session', null));
});

test('settings page reports the inbox webhook without exposing the HMAC key', function () {
    config(['services.waha.webhook_hmac_key' => 'segredo-hmac-do-webhook', 'services.waha.api_key' => 'segredo-api-key']);

    $response = $this->actingAs(User::factory()->create())->get(route('whatsapp-settings.edit'));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('settings/whatsapp')
        ->where('webhookEnabled', true)
        ->where('webhookUrl', route('api.whatsapp.waha.webhook')));

    expect($response->getContent())
        ->not->toContain('segredo-hmac-do-webhook')
        ->not->toContain('segredo-api-key');
});

test('the old leads settings routes no longer exist', function () {
    $admin = User::factory()->create();

    $this->actingAs($admin)->get('/settings/leads')->assertNotFound();
    $this->actingAs($admin)->put('/settings/leads/whatsapp', companyWhatsappPayload())->assertNotFound();
    $this->actingAs($admin)->getJson('/settings/leads/whatsapp/status')->assertNotFound();
});
