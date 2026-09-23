<?php

use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\User;
use App\Services\LeadWhatsappService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Inertia\Testing\AssertableInertia as Assert;

const N8N_WHATSAPP_TEST_URL = 'https://n8n.test/webhook/crm-enviar-whatsapp-secret-path';
const N8N_WHATSAPP_TEST_TOKEN = 'n8n-header-token-que-nao-pode-vazar';

beforeEach(function () {
    config([
        'services.n8n.whatsapp_webhook_url' => N8N_WHATSAPP_TEST_URL,
        'services.n8n.whatsapp_webhook_header' => 'X-CRM-Token',
        'services.n8n.whatsapp_webhook_token' => N8N_WHATSAPP_TEST_TOKEN,
        'services.ab_prospect.token' => 'prospect-token-que-nao-pode-vazar',
    ]);

    Http::preventStrayRequests();
});

function leadForWhatsapp(array $attributes = []): Lead
{
    return Lead::query()->create([
        'company_name' => 'Assistência Técnica ABC',
        'product' => 'vetoros',
        'status' => 'new',
        'whatsapp' => '51999998888',
        ...$attributes,
    ]);
}

function n8nSuccessResponse(): array
{
    return [
        'success' => true,
        'message' => 'Mensagem enviada ao WAHA e registrada no CRM',
        'message_id' => '3EB05962A724B2EB80AD88',
        'status' => 'PENDING',
        'activity_id' => 11,
    ];
}

test('sending WhatsApp requires authentication', function () {
    Http::fake();
    $lead = leadForWhatsapp();

    $this->post(route('leads.whatsapp.store', $lead), ['message' => 'Olá'])
        ->assertRedirect(route('login'));

    Http::assertNothingSent();
});

test('users without access to leads cannot send WhatsApp', function () {
    Http::fake();
    $lead = leadForWhatsapp();

    $this->actingAs(User::factory()->reader()->create())
        ->post(route('leads.whatsapp.store', $lead), ['message' => 'Olá'])
        ->assertForbidden();

    Http::assertNothingSent();
});

test('authorized user sends the WhatsApp through the n8n webhook', function () {
    Http::fake([N8N_WHATSAPP_TEST_URL => Http::response(n8nSuccessResponse())]);
    $user = User::factory()->create();
    $lead = leadForWhatsapp();

    $this->actingAs($user)
        ->from(route('leads.edit', $lead))
        ->post(route('leads.whatsapp.store', $lead), ['message' => 'Olá, tudo bem? Somos da ABrasil Sistemas.'])
        ->assertRedirect(route('leads.edit', $lead))
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast.type', 'success');

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request) => $request->url() === N8N_WHATSAPP_TEST_URL
        && $request->method() === 'POST'
        && $request->hasHeader('X-CRM-Token', N8N_WHATSAPP_TEST_TOKEN)
        && $request->data() === [
            'prospect_id' => $lead->id,
            'nome' => 'Assistência Técnica ABC',
            'whatsapp' => '5551999998888',
            'mensagem' => 'Olá, tudo bem? Somos da ABrasil Sistemas.',
        ]);
});

test('the webhook auth header name comes from configuration', function () {
    config(['services.n8n.whatsapp_webhook_header' => 'X-Outro-Header']);
    Http::fake([N8N_WHATSAPP_TEST_URL => Http::response(n8nSuccessResponse())]);
    $lead = leadForWhatsapp();

    $this->actingAs(User::factory()->create())
        ->post(route('leads.whatsapp.store', $lead), ['message' => 'Mensagem'])
        ->assertSessionHasNoErrors();

    Http::assertSent(fn (Request $request) => $request->hasHeader('X-Outro-Header', N8N_WHATSAPP_TEST_TOKEN)
        && ! $request->hasHeader('X-CRM-Token'));
});

test('missing webhook auth does not call n8n nor leak anything', function (string $key) {
    config([$key => '']);
    Http::fake();
    Log::spy();
    $lead = leadForWhatsapp();

    $this->actingAs(User::factory()->create())
        ->post(route('leads.whatsapp.store', $lead), ['message' => 'Mensagem'])
        ->assertSessionHasErrors(['whatsapp' => 'O envio de WhatsApp não está configurado. Avise o administrador do sistema.']);

    Http::assertNothingSent();
    expect(LeadActivity::query()->count())->toBe(0);
    Log::shouldHaveReceived('error')->once()->withArgs(fn (string $message, array $context) => ! str_contains(json_encode([$message, $context]), N8N_WHATSAPP_TEST_TOKEN));
})->with([
    'token ausente' => 'services.n8n.whatsapp_webhook_token',
    'header ausente' => 'services.n8n.whatsapp_webhook_header',
]);

test('n8n rejecting the auth (403) shows a friendly error without the token', function () {
    Http::fake([N8N_WHATSAPP_TEST_URL => Http::response('Authorization data is wrong!', 403)]);
    Log::spy();
    $lead = leadForWhatsapp();

    $this->actingAs(User::factory()->create())
        ->post(route('leads.whatsapp.store', $lead), ['message' => 'Mensagem'])
        ->assertSessionHasErrors(['whatsapp' => 'O serviço de WhatsApp recusou o envio. Tente novamente em instantes.']);

    expect(json_encode(session('errors')->getMessages()))
        ->not->toContain(N8N_WHATSAPP_TEST_TOKEN)
        ->not->toContain('X-CRM-Token');
    expect(LeadActivity::query()->count())->toBe(0);
    Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $message, array $context) => ! str_contains(json_encode([$message, $context]), N8N_WHATSAPP_TEST_TOKEN));
});

test('the web controller does not create a LeadActivity of its own', function () {
    Http::fake([N8N_WHATSAPP_TEST_URL => Http::response(n8nSuccessResponse())]);
    $lead = leadForWhatsapp();

    $this->actingAs(User::factory()->create())
        ->post(route('leads.whatsapp.store', $lead), ['message' => 'Mensagem de teste'])
        ->assertSessionHasNoErrors();

    expect(LeadActivity::query()->count())->toBe(0);
});

test('prospect id, name and destination come from the route lead, not from the body', function () {
    Http::fake([N8N_WHATSAPP_TEST_URL => Http::response(n8nSuccessResponse())]);
    $lead = leadForWhatsapp();
    $other = leadForWhatsapp(['company_name' => 'Outro prospect', 'whatsapp' => '51911112222']);

    $this->actingAs(User::factory()->create())
        ->post(route('leads.whatsapp.store', $lead), [
            'message' => 'Mensagem',
            'prospect_id' => $other->id,
            'lead_id' => $other->id,
            'nome' => 'Nome forjado',
            'whatsapp' => '5511900000000',
        ])
        ->assertSessionHasNoErrors();

    Http::assertSent(fn (Request $request) => $request['prospect_id'] === $lead->id
        && $request['nome'] === 'Assistência Técnica ABC'
        && $request['whatsapp'] === '5551999998888');
});

test('WhatsApp numbers are normalized before sending', function (string $stored, string $expected) {
    Http::fake([N8N_WHATSAPP_TEST_URL => Http::response(n8nSuccessResponse())]);
    $lead = leadForWhatsapp(['whatsapp' => $stored]);

    $this->actingAs(User::factory()->create())
        ->post(route('leads.whatsapp.store', $lead), ['message' => 'Mensagem'])
        ->assertSessionHasNoErrors();

    Http::assertSent(fn (Request $request) => $request['whatsapp'] === $expected);
})->with([
    'celular com DDD' => ['51999998888', '5551999998888'],
    'fixo com DDD' => ['5133334444', '555133334444'],
    'já com 55' => ['5551999998888', '5551999998888'],
    'com máscara' => ['+55 (51) 99999-8888', '5551999998888'],
    'DDD 55 sem código do país' => ['55999998888', '5555999998888'],
]);

test('lead without WhatsApp does not call n8n', function (?string $whatsapp) {
    Http::fake();
    $lead = leadForWhatsapp(['whatsapp' => $whatsapp]);

    $this->actingAs(User::factory()->create())
        ->post(route('leads.whatsapp.store', $lead), ['message' => 'Mensagem'])
        ->assertSessionHasErrors(['whatsapp' => 'Este prospect não possui WhatsApp cadastrado.']);

    Http::assertNothingSent();
})->with([null, '']);

test('lead with an invalid WhatsApp does not call n8n', function (string $whatsapp) {
    Http::fake();
    $lead = leadForWhatsapp(['whatsapp' => $whatsapp]);

    $this->actingAs(User::factory()->create())
        ->post(route('leads.whatsapp.store', $lead), ['message' => 'Mensagem'])
        ->assertSessionHasErrors(['whatsapp' => 'O WhatsApp cadastrado neste prospect não é um número válido.']);

    Http::assertNothingSent();
})->with(['123', '999998888', '4451999998888', 'sem número']);

test('empty or oversized messages are rejected without calling n8n', function (mixed $message) {
    Http::fake();
    $lead = leadForWhatsapp();

    $this->actingAs(User::factory()->create())
        ->post(route('leads.whatsapp.store', $lead), ['message' => $message])
        ->assertSessionHasErrors('message');

    Http::assertNothingSent();
})->with([
    'vazia' => '',
    'só espaços' => '   ',
    'ausente' => null,
    'longa demais' => str_repeat('a', 4001),
]);

test('n8n timeout shows a friendly error', function () {
    Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out after 20001 milliseconds for '.N8N_WHATSAPP_TEST_URL));
    $lead = leadForWhatsapp();

    $this->actingAs(User::factory()->create())
        ->post(route('leads.whatsapp.store', $lead), ['message' => 'Mensagem'])
        ->assertSessionHasErrors(['whatsapp' => 'O serviço de WhatsApp demorou para responder. Confira o histórico antes de reenviar para evitar mensagem duplicada.']);

    expect(LeadActivity::query()->count())->toBe(0);
});

test('n8n unavailable shows a friendly error', function () {
    Http::fake(fn () => throw new ConnectionException('cURL error 7: Failed to connect to n8n.test port 443: Connection refused'));
    $lead = leadForWhatsapp();

    $this->actingAs(User::factory()->create())
        ->post(route('leads.whatsapp.store', $lead), ['message' => 'Mensagem'])
        ->assertSessionHasErrors(['whatsapp' => 'Não foi possível conectar ao serviço de WhatsApp. Tente novamente em instantes.']);
});

test('n8n HTTP errors show a friendly error without internal details', function (int $status) {
    Http::fake([N8N_WHATSAPP_TEST_URL => Http::response(['message' => 'Stack trace interno do workflow'], $status)]);
    $lead = leadForWhatsapp();

    $this->actingAs(User::factory()->create())
        ->post(route('leads.whatsapp.store', $lead), ['message' => 'Mensagem'])
        ->assertSessionHasErrors(['whatsapp' => 'O serviço de WhatsApp recusou o envio. Tente novamente em instantes.']);

    expect(session('errors')->get('whatsapp')[0])->not->toContain('Stack trace');
})->with([500, 502, 404, 422]);

test('n8n success=false shows a friendly error', function () {
    Http::fake([N8N_WHATSAPP_TEST_URL => Http::response(['success' => false, 'message' => 'WAHA session STOPPED'])]);
    $lead = leadForWhatsapp();

    $this->actingAs(User::factory()->create())
        ->post(route('leads.whatsapp.store', $lead), ['message' => 'Mensagem'])
        ->assertSessionHasErrors(['whatsapp' => 'A mensagem não pôde ser enviada pelo WhatsApp. Verifique o número e tente novamente.']);
});

test('invalid n8n responses show a friendly error', function (mixed $body) {
    Http::fake([N8N_WHATSAPP_TEST_URL => Http::response($body)]);
    $lead = leadForWhatsapp();

    $this->actingAs(User::factory()->create())
        ->post(route('leads.whatsapp.store', $lead), ['message' => 'Mensagem'])
        ->assertSessionHasErrors(['whatsapp' => 'O serviço de WhatsApp retornou uma resposta inesperada. Confira o histórico antes de reenviar.']);
})->with([
    'html' => '<html>Workflow was started</html>',
    'vazio' => '',
    'json sem success' => [['message' => 'Workflow was started']],
]);

test('missing webhook configuration does not call anything', function () {
    config(['services.n8n.whatsapp_webhook_url' => '']);
    Http::fake();
    $lead = leadForWhatsapp();

    $this->actingAs(User::factory()->create())
        ->post(route('leads.whatsapp.store', $lead), ['message' => 'Mensagem'])
        ->assertSessionHasErrors(['whatsapp' => 'O envio de WhatsApp não está configurado. Avise o administrador do sistema.']);

    Http::assertNothingSent();
});

test('manual WhatsApp sending is throttled', function () {
    Http::fake([N8N_WHATSAPP_TEST_URL => Http::response(n8nSuccessResponse())]);
    $user = User::factory()->create();
    $lead = leadForWhatsapp();

    foreach (range(1, 10) as $attempt) {
        $this->actingAs($user)
            ->post(route('leads.whatsapp.store', $lead), ['message' => "Mensagem {$attempt}"])
            ->assertSessionHasNoErrors();
    }

    $this->actingAs($user)
        ->post(route('leads.whatsapp.store', $lead), ['message' => 'Mensagem 11'])
        ->assertTooManyRequests();

    Http::assertSentCount(10);
});

test('lead edit page exposes only the normalized destination, never internal URLs or tokens', function () {
    $this->withoutVite();
    $lead = leadForWhatsapp(['whatsapp' => '51999998888']);

    $response = $this->actingAs(User::factory()->create())
        ->get(route('leads.edit', $lead))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('leads/edit')
            ->where('whatsappDestination', '5551999998888'));

    $content = $response->getContent();

    expect($content)
        ->not->toContain('n8n.test')
        ->not->toContain('crm-enviar-whatsapp-secret-path')
        ->not->toContain('prospect-token-que-nao-pode-vazar')
        ->not->toContain(N8N_WHATSAPP_TEST_TOKEN)
        ->not->toContain('X-CRM-Token');
});

test('lead edit page has no destination when the lead has no valid WhatsApp', function () {
    $this->withoutVite();
    $lead = leadForWhatsapp(['whatsapp' => null]);

    $this->actingAs(User::factory()->create())
        ->get(route('leads.edit', $lead))
        ->assertInertia(fn (Assert $page) => $page->where('whatsappDestination', null));
});

test('number normalization keeps the rule used by the lead list wa.me link', function () {
    expect(LeadWhatsappService::normalizeNumber('51999998888'))->toBe('5551999998888')
        ->and(LeadWhatsappService::normalizeNumber('5551999998888'))->toBe('5551999998888')
        ->and(LeadWhatsappService::normalizeNumber(null))->toBeNull()
        ->and(LeadWhatsappService::normalizeNumber('12345'))->toBeNull();
});
