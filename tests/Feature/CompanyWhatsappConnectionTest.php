<?php

use App\Models\User;
use App\Services\CompanyWhatsappConnection;
use App\Services\CompanyWhatsappSettings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

const WAHA_TEST_URL = 'http://waha.test';
const WAHA_TEST_KEY = 'waha-secret-api-key';
const WAHA_TEST_SESSION = 'sessao-empresa';

beforeEach(function () {
    config([
        'services.waha.base_url' => WAHA_TEST_URL,
        'services.waha.api_key' => WAHA_TEST_KEY,
        'services.waha.timeout' => 5,
    ]);

    Http::preventStrayRequests();

    app(CompanyWhatsappSettings::class)->save([
        'enabled' => true,
        'number' => '51998931325',
        'provider' => 'waha',
        'session' => WAHA_TEST_SESSION,
    ]);
});

function wahaSession(string $status, ?array $me = null): array
{
    return ['name' => WAHA_TEST_SESSION, 'status' => $status, 'me' => $me];
}

function wahaSessionUrl(string $suffix = ''): string
{
    return WAHA_TEST_URL.'/api/sessions/'.WAHA_TEST_SESSION.$suffix;
}

function wahaQrUrl(): string
{
    return WAHA_TEST_URL.'/api/'.WAHA_TEST_SESSION.'/auth/qr*';
}

function fakePng(): string
{
    return "\x89PNG\r\n\x1a\n".str_repeat("\0", 32);
}

function wahaAdmin(): User
{
    return User::factory()->create();
}

test('WORKING session is shown as connected with the authenticated account', function () {
    Http::fake([
        wahaSessionUrl() => Http::response(wahaSession('WORKING', [
            'id' => '5551998931325@c.us',
            'pushName' => 'Anderson Brasil',
            'lid' => '123456789@lid',
        ])),
    ]);

    $this->actingAs(wahaAdmin())
        ->getJson(route('lead-settings.whatsapp.status'))
        ->assertOk()
        ->assertJson([
            'state' => 'connected',
            'label' => 'Conectado',
            'session' => WAHA_TEST_SESSION,
            'configured_number' => '5551998931325',
            'waha_status' => 'WORKING',
            'account' => [
                'id' => '5551998931325@c.us',
                'number' => '5551998931325',
                'name' => 'Anderson Brasil',
                'lid' => '123456789@lid',
            ],
            'number_matches' => true,
        ]);

    Http::assertSent(fn (Request $request) => $request->url() === wahaSessionUrl()
        && $request->hasHeader('X-Api-Key', WAHA_TEST_KEY));
});

test('SCAN_QR_CODE session is waiting for the QR Code', function () {
    Http::fake([wahaSessionUrl() => Http::response(wahaSession('SCAN_QR_CODE'))]);

    $this->actingAs(wahaAdmin())
        ->getJson(route('lead-settings.whatsapp.status'))
        ->assertOk()
        ->assertJson([
            'state' => 'scan_qr_code',
            'label' => 'Aguardando leitura do QR Code',
            'account' => null,
            'number_matches' => null,
        ]);
});

test('WAHA statuses are mapped to panel states', function (string $wahaStatus, string $state, string $label) {
    Http::fake([wahaSessionUrl() => Http::response(wahaSession($wahaStatus))]);

    $this->actingAs(wahaAdmin())
        ->getJson(route('lead-settings.whatsapp.status'))
        ->assertOk()
        ->assertJson(['state' => $state, 'label' => $label, 'waha_status' => $wahaStatus]);
})->with([
    'FAILED' => ['FAILED', 'failed', 'Falha na conexão'],
    'STOPPED' => ['STOPPED', 'disconnected', 'Desconectado'],
    'STARTING' => ['STARTING', 'connecting', 'Conectando'],
    'desconhecido' => ['SOMETHING_NEW', 'unknown', 'Status desconhecido'],
]);

test('the QR Code PNG is proxied without being parsed as JSON', function () {
    Http::fake([
        wahaSessionUrl() => Http::response(wahaSession('SCAN_QR_CODE')),
        wahaQrUrl() => Http::response(fakePng(), 200, ['Content-Type' => 'image/png']),
    ]);

    $response = $this->actingAs(wahaAdmin())
        ->get(route('lead-settings.whatsapp.qr'))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');

    expect($response->getContent())->toBe(fakePng());
    expect($response->headers->get('Cache-Control'))->toContain('no-store');

    Http::assertSent(fn (Request $request) => str_starts_with($request->url(), WAHA_TEST_URL.'/api/'.WAHA_TEST_SESSION.'/auth/qr')
        && $request->hasHeader('Accept', 'image/png')
        && $request->hasHeader('X-Api-Key', WAHA_TEST_KEY));
});

test('the QR Code is refused when the session is not waiting for it', function () {
    Http::fake([wahaSessionUrl() => Http::response(wahaSession('WORKING', ['id' => '5551998931325@c.us']))]);

    $this->actingAs(wahaAdmin())
        ->getJson(route('lead-settings.whatsapp.qr'))
        ->assertStatus(409)
        ->assertJsonPath('message', fn (string $message) => str_contains($message, 'QR Code não está disponível'));

    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/auth/qr'));
});

test('an invalid QR Code image is rejected', function () {
    Http::fake([
        wahaSessionUrl() => Http::response(wahaSession('SCAN_QR_CODE')),
        wahaQrUrl() => Http::response('not a png', 200, ['Content-Type' => 'image/png']),
    ]);

    $this->actingAs(wahaAdmin())
        ->getJson(route('lead-settings.whatsapp.qr'))
        ->assertStatus(502);
});

test('connect restarts a stopped or failed session', function (string $wahaStatus) {
    Http::fake([
        wahaSessionUrl('/restart') => Http::response(wahaSession('STARTING')),
        wahaSessionUrl() => Http::sequence()
            ->push(wahaSession($wahaStatus))
            ->push(wahaSession('SCAN_QR_CODE')),
    ]);

    $this->actingAs(wahaAdmin())
        ->postJson(route('lead-settings.whatsapp.connect'))
        ->assertOk()
        ->assertJson(['state' => 'scan_qr_code']);

    Http::assertSent(fn (Request $request) => $request->method() === 'POST' && $request->url() === wahaSessionUrl('/restart'));
    Http::assertNotSent(fn (Request $request) => $request->method() === 'DELETE' || $request->url() === WAHA_TEST_URL.'/api/sessions');
})->with(['STOPPED', 'FAILED']);

test('connect does not restart a session that is already working or waiting for the QR Code', function (string $wahaStatus) {
    Http::fake([wahaSessionUrl() => Http::response(wahaSession($wahaStatus, $wahaStatus === 'WORKING' ? ['id' => '5551998931325@c.us'] : null))]);

    $this->actingAs(wahaAdmin())
        ->postJson(route('lead-settings.whatsapp.connect'))
        ->assertOk();

    Http::assertNotSent(fn (Request $request) => str_ends_with($request->url(), '/restart'));
})->with(['WORKING', 'SCAN_QR_CODE', 'STARTING']);

test('connect never creates a session that does not exist in WAHA', function () {
    Http::fake([wahaSessionUrl() => Http::response(['message' => 'Session not found'], 404)]);

    $this->actingAs(wahaAdmin())
        ->postJson(route('lead-settings.whatsapp.connect'))
        ->assertNotFound()
        ->assertJsonPath('message', fn (string $message) => str_contains($message, 'não existe no WAHA'));

    Http::assertSentCount(1);
});

test('disconnect logs out the session without deleting it or the settings', function () {
    Http::fake([
        wahaSessionUrl('/logout') => Http::response([], 201),
        wahaSessionUrl() => Http::response(wahaSession('SCAN_QR_CODE')),
    ]);

    $this->actingAs(wahaAdmin())
        ->postJson(route('lead-settings.whatsapp.disconnect'))
        ->assertOk()
        ->assertJson(['state' => 'scan_qr_code']);

    Http::assertSent(fn (Request $request) => $request->method() === 'POST' && $request->url() === wahaSessionUrl('/logout'));
    Http::assertNotSent(fn (Request $request) => $request->method() === 'DELETE');

    expect(app(CompanyWhatsappSettings::class)->current())->toMatchArray([
        'enabled' => true,
        'number' => '5551998931325',
        'session' => WAHA_TEST_SESSION,
    ]);
});

test('WAHA HTTP errors become friendly messages', function () {
    Http::fake([
        wahaSessionUrl() => Http::response(['message' => 'boom'], 500),
        wahaSessionUrl('/logout') => Http::response(['message' => 'boom'], 500),
    ]);

    $this->actingAs(wahaAdmin())
        ->getJson(route('lead-settings.whatsapp.status'))
        ->assertOk()
        ->assertJson(['state' => 'error', 'label' => 'Erro'])
        ->assertJsonPath('message', fn (string $message) => str_contains($message, 'Não foi possível falar com o serviço de WhatsApp'));

    $this->actingAs(wahaAdmin())
        ->postJson(route('lead-settings.whatsapp.disconnect'))
        ->assertStatus(502);
});

test('WAHA timeouts and connection failures are handled', function () {
    Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out after 5001 milliseconds'));

    $this->actingAs(wahaAdmin())
        ->getJson(route('lead-settings.whatsapp.status'))
        ->assertOk()
        ->assertJson(['state' => 'error']);

    $this->actingAs(wahaAdmin())
        ->postJson(route('lead-settings.whatsapp.connect'))
        ->assertStatus(502);
});

test('configured number is compared to me.id after normalization', function (string $meId, bool $matches) {
    expect(CompanyWhatsappConnection::sameNumber('5551998931325', $meId))->toBe($matches);
})->with([
    'igual' => ['5551998931325@c.us', true],
    'sem o nono dígito' => ['555198931325@c.us', true],
    's.whatsapp.net' => ['5551998931325@s.whatsapp.net', true],
    'outro número' => ['555195179173@c.us', false],
    'outro DDD' => ['5511998931325@c.us', false],
]);

test('a different connected number is reported as divergent and never saved', function () {
    Http::fake([
        wahaSessionUrl() => Http::response(wahaSession('WORKING', [
            'id' => '555195179173@c.us',
            'pushName' => 'Outra conta',
        ])),
    ]);

    $this->actingAs(wahaAdmin())
        ->getJson(route('lead-settings.whatsapp.status'))
        ->assertOk()
        ->assertJson([
            'state' => 'connected',
            'configured_number' => '5551998931325',
            'account' => ['number' => '555195179173', 'name' => 'Outra conta'],
            'number_matches' => false,
        ]);

    expect(app(CompanyWhatsappSettings::class)->current()['number'])->toBe('5551998931325');
});

test('guests and readers cannot manage the WhatsApp connection', function () {
    Http::fake();

    $this->getJson(route('lead-settings.whatsapp.status'))->assertUnauthorized();
    $this->postJson(route('lead-settings.whatsapp.connect'))->assertUnauthorized();

    $reader = User::factory()->reader()->create();

    $this->actingAs($reader)->getJson(route('lead-settings.whatsapp.status'))->assertForbidden();
    $this->actingAs($reader)->postJson(route('lead-settings.whatsapp.connect'))->assertForbidden();
    $this->actingAs($reader)->get(route('lead-settings.whatsapp.qr'))->assertForbidden();
    $this->actingAs($reader)->postJson(route('lead-settings.whatsapp.disconnect'))->assertForbidden();

    Http::assertNothingSent();
});

test('the WAHA API key is never exposed in responses', function () {
    Http::fake([
        wahaSessionUrl('/restart') => Http::response(wahaSession('STARTING')),
        wahaSessionUrl('/logout') => Http::response([]),
        wahaSessionUrl() => Http::response(wahaSession('WORKING', ['id' => '5551998931325@c.us', 'pushName' => 'Empresa'])),
    ]);

    $admin = wahaAdmin();

    foreach ([
        $this->actingAs($admin)->getJson(route('lead-settings.whatsapp.status')),
        $this->actingAs($admin)->postJson(route('lead-settings.whatsapp.connect')),
        $this->actingAs($admin)->postJson(route('lead-settings.whatsapp.disconnect')),
        $this->actingAs($admin)->get(route('lead-settings.edit')),
    ] as $response) {
        expect($response->getContent())->not->toContain(WAHA_TEST_KEY);
    }
});

test('the session always comes from the settings, never from the browser', function () {
    Http::fake([
        wahaSessionUrl('/restart') => Http::response(wahaSession('STARTING')),
        wahaSessionUrl() => Http::sequence()
            ->push(wahaSession('WORKING', ['id' => '5551998931325@c.us']))
            ->push(wahaSession('STOPPED'))
            ->push(wahaSession('SCAN_QR_CODE')),
    ]);

    $admin = wahaAdmin();

    $this->actingAs($admin)
        ->getJson(route('lead-settings.whatsapp.status', ['session' => 'sessao-de-outro']))
        ->assertOk()
        ->assertJsonPath('session', WAHA_TEST_SESSION);

    $this->actingAs($admin)
        ->postJson(route('lead-settings.whatsapp.connect'), ['session' => 'sessao-de-outro'])
        ->assertOk()
        ->assertJsonPath('session', WAHA_TEST_SESSION);

    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'sessao-de-outro'));
});

test('connection actions require a saved WAHA session', function () {
    app(CompanyWhatsappSettings::class)->save([
        'enabled' => false,
        'number' => null,
        'provider' => 'waha',
        'session' => null,
    ]);

    Http::fake();

    $this->actingAs(wahaAdmin())
        ->getJson(route('lead-settings.whatsapp.status'))
        ->assertOk()
        ->assertJson(['state' => 'not_configured']);

    $this->actingAs(wahaAdmin())
        ->postJson(route('lead-settings.whatsapp.connect'))
        ->assertUnprocessable();

    Http::assertNothingSent();
});
