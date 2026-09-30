<?php

use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\ProspectProduct;
use App\Models\Setting;
use App\Models\User;
use App\Services\HostResolver;
use App\Services\ProductRegistrationChecker;
use App\Services\RegistrationApiUrl;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * CRM-WA-29: acompanhamento de cadastro por produto. manual = como antes; api = o CRM consulta a
 * API do produto e, com cadastro confirmado, inicia o trial pela mesma regra do início manual
 * (Lead::startTrial). Erro de API nunca vale como "não cadastrado" e não muda nada comercial.
 */

const REG_CHECK_URL = 'https://registro.produto.example.com/api/crm/registration';
const REG_CHECK_TOKEN = 'tok_secreto_crm_wa_29_abcdef123456';
const REG_CHECK_PUBLIC_IP = '93.184.216.34';

beforeEach(function () {
    // Segunda, 28/09/2026 12:00 em São Paulo (15:00 UTC).
    $this->travelTo(now('UTC')->setDate(2026, 9, 28)->setTime(15, 0, 0));
    regCheckResolveTo([REG_CHECK_PUBLIC_IP]);
    Http::preventStrayRequests();
});

/** @param  list<string>  $addresses */
function regCheckResolveTo(array $addresses): void
{
    app()->instance(HostResolver::class, new class($addresses) extends HostResolver
    {
        /** @param  list<string>  $addresses */
        public function __construct(private array $addresses) {}

        public function ipv4(string $host): array
        {
            return $this->addresses;
        }
    });
}

/** @param  array<string, mixed>  $attributes */
function regCheckProduct(array $attributes = []): ProspectProduct
{
    return ProspectProduct::query()->create([
        'name' => 'Produto API',
        'trial_days' => 14,
        'registration_check_mode' => 'api',
        'registration_api_url' => REG_CHECK_URL,
        'registration_lookup_field' => 'whatsapp',
        'registration_api_token' => REG_CHECK_TOKEN,
        ...$attributes,
    ]);
}

/** @param  array<string, mixed>  $attributes */
function regCheckLead(?ProspectProduct $product = null, array $attributes = []): Lead
{
    return Lead::query()->create([
        'company_name' => 'Lead cadastro '.uniqid(),
        'status' => 'contacted',
        'whatsapp' => '(51) 99517-9173',
        'email' => 'Contato@Cliente.com.br',
        'prospect_product_id' => ($product ?? regCheckProduct())->id,
        ...$attributes,
    ]);
}

/** Estado comercial do lead, cru do banco (sem os campos da verificação). */
function regCheckCommercial(Lead $lead): array
{
    $row = (array) DB::table('leads')->where('id', $lead->id)->first();

    return [
        collect($row)->except(['registration_checked_at', 'registration_check_status', 'registration_check_error'])->all(),
        LeadActivity::query()->where('lead_id', $lead->id)->count(),
    ];
}

function regCheckRun(Lead $lead): mixed
{
    return app(ProductRegistrationChecker::class)->checkAndRecord($lead->fresh());
}

/** @param  array<string, mixed>|string  $body */
function regCheckFake(array|string $body, int $status = 200): void
{
    Http::fake([REG_CHECK_URL => Http::response($body, $status)]);
}

// A / K — produto manual

test('A/K: a manual product keeps the manual trial start and never calls any API', function () {
    Http::fake();
    $product = ProspectProduct::query()->create(['name' => 'Manual', 'trial_days' => 7]);
    $lead = regCheckLead($product);

    expect($product->fresh()->registration_check_mode)->toBe('manual')
        ->and(regCheckRun($lead))->toBeNull()
        ->and($lead->fresh()->registrationCheck())->toBeNull();

    $this->artisan('leads:check-registrations')->assertSuccessful();
    $this->actingAs(User::factory()->create())->post(route('leads.start-trial', $lead))->assertRedirect();

    $lead->refresh();
    expect($lead->trial_started_at->toDateTimeString())->toBe('2026-09-28 15:00:00')
        ->and($lead->trial_ends_at->toDateTimeString())->toBe('2026-10-05 15:00:00')
        ->and($lead->registration_checked_at)->toBeNull();

    Http::assertNothingSent();
});

// B — não cadastrado

test('B: registered=false leaves the trial not started and records only the check', function () {
    regCheckFake(['registered' => false]);
    $lead = regCheckLead();
    $before = regCheckCommercial($lead);

    $result = regCheckRun($lead);

    expect($result->status)->toBe('not_registered')
        ->and(regCheckCommercial($lead))->toBe($before);

    $lead->refresh();
    expect($lead->trial_started_at)->toBeNull()
        ->and($lead->registration_check_status)->toBe('not_registered')
        ->and($lead->registration_check_error)->toBeNull()
        ->and($lead->registration_checked_at->toDateTimeString())->toBe('2026-09-28 15:00:00');

    Http::assertSent(fn (Request $request) => $request->url() === REG_CHECK_URL
        && $request->method() === 'POST'
        && $request->hasHeader('Authorization', 'Bearer '.REG_CHECK_TOKEN)
        && $request->data() === ['lookup_field' => 'whatsapp', 'lookup_value' => '5551995179173']);
});

test('the email lookup sends the lead email trimmed and lowercased', function () {
    regCheckFake(['registered' => false]);
    $lead = regCheckLead(regCheckProduct(['registration_lookup_field' => 'email']));

    regCheckRun($lead);

    Http::assertSent(fn (Request $request) => $request->data() === ['lookup_field' => 'email', 'lookup_value' => 'contato@cliente.com.br']);
});

// C / J — cadastrado

test('C/J: registered=true starts the trial at registered_at with the product trial days', function () {
    regCheckFake(['registered' => true, 'registered_at' => '2026-09-27T14:30:00Z']);
    $lead = regCheckLead();

    $result = regCheckRun($lead);

    expect($result->status)->toBe('registered')
        ->and($result->trialStarted)->toBeTrue();

    $lead->refresh();
    expect($lead->trial_started_at->toDateTimeString())->toBe('2026-09-27 14:30:00')
        ->and($lead->trial_ends_at->toDateTimeString())->toBe('2026-10-11 14:30:00')
        ->and($lead->trial_state)->toBe('active')
        ->and($lead->status)->toBe('contacted')
        ->and($lead->registration_check_status)->toBe('registered');

    $notes = LeadActivity::query()->where('lead_id', $lead->id)->get();
    expect($notes)->toHaveCount(1)
        ->and($notes[0]->user_id)->toBeNull()
        ->and($notes[0]->type)->toBe('note')
        ->and($notes[0]->description)->toBe('Período de teste iniciado (Produto API, 14 dias). Vencimento: 11/10/2026 11:30. Cadastro confirmado pela API do produto em 27/09/2026 11:30.');
});

test('J: registered_at with an offset is converted to the same instant', function () {
    regCheckFake(['registered' => true, 'registered_at' => '2026-09-28T09:15:42-03:00']);
    $lead = regCheckLead(regCheckProduct(['trial_days' => 0]));

    regCheckRun($lead);

    $lead->refresh();
    expect($lead->trial_started_at->toDateTimeString())->toBe('2026-09-28 12:15:42')
        ->and($lead->trial_ends_at->toDateTimeString())->toBe('2026-09-28 12:15:42');
});

test('J: registered_at slightly ahead of the clock starts the trial now, never in the future', function () {
    regCheckFake(['registered' => true, 'registered_at' => '2026-09-28T15:03:00Z']);
    $lead = regCheckLead();

    regCheckRun($lead);

    expect($lead->fresh()->trial_started_at->toDateTimeString())->toBe('2026-09-28 15:00:00');
});

// D — repetição

test('D: a repeated positive answer never restarts or changes the trial', function () {
    regCheckFake(['registered' => true, 'registered_at' => '2026-09-27T14:30:00Z']);
    $lead = regCheckLead();
    $stale = $lead->fresh();

    regCheckRun($lead);
    $after = regCheckCommercial($lead);

    // Lead já com teste: fora da verificação, sem chamada.
    regCheckFake(['registered' => true, 'registered_at' => '2026-09-28T10:00:00Z']);
    expect(regCheckRun($lead))->toBeNull();

    // Instância carregada antes do início (outra execução em paralelo): o UPDATE condicional recusa.
    $result = app(ProductRegistrationChecker::class)->checkAndRecord($stale);
    expect($result->status)->toBe('registered')
        ->and($result->trialStarted)->toBeFalse()
        ->and(regCheckCommercial($lead))->toBe($after);

    $this->artisan('leads:check-registrations')->assertSuccessful();
    expect(regCheckCommercial($lead))->toBe($after);
});

// E / F / G — falhas: nenhuma alteração comercial

test('E/F/G: API failures change nothing commercial and are recorded as errors', function (Closure $fake, string $error) {
    $fake();
    $lead = regCheckLead();
    $before = regCheckCommercial($lead);
    Log::spy();

    $result = regCheckRun($lead);

    expect($result->status)->toBe('error')
        ->and($result->error)->toBe($error)
        ->and(regCheckCommercial($lead))->toBe($before);

    $lead->refresh();
    expect($lead->trial_started_at)->toBeNull()
        ->and($lead->registration_check_status)->toBe('error')
        ->and($lead->registration_check_error)->toBe($error);

    Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $message, array $context) => $context['error'] === $error
        && $context['lead_id'] === $lead->id
        && $context['host'] === 'registro.produto.example.com'
        && ! str_contains(json_encode($context, JSON_UNESCAPED_SLASHES), REG_CHECK_TOKEN)
        && ! str_contains(json_encode($context, JSON_UNESCAPED_SLASHES), '/api/crm/registration')
        && ! str_contains(json_encode($context, JSON_UNESCAPED_SLASHES), '99517'));
})->with([
    '401' => [fn () => regCheckFake(['message' => 'unauthorized'], 401), 'unauthorized'],
    '403' => [fn () => regCheckFake('', 403), 'unauthorized'],
    '404' => [fn () => regCheckFake(['registered' => false], 404), 'not_found'],
    '422' => [fn () => regCheckFake(['errors' => []], 422), 'unprocessable'],
    '429' => [fn () => regCheckFake('', 429), 'rate_limited'],
    '500' => [fn () => regCheckFake('', 500), 'server_error'],
    '503' => [fn () => regCheckFake(['registered' => false], 503), 'server_error'],
    'redirect not followed' => [fn () => Http::fake([REG_CHECK_URL => Http::response('', 302, ['Location' => 'http://169.254.169.254/'])]), 'redirect'],
    '201' => [fn () => regCheckFake(['registered' => false], 201), 'unexpected_status'],
    'timeout' => [fn () => Http::fake([REG_CHECK_URL => fn () => throw new ConnectionException('cURL error 28: Operation timed out after 10001 milliseconds')]), 'timeout'],
    'connection refused' => [fn () => Http::fake([REG_CHECK_URL => fn () => throw new ConnectionException('cURL error 7: Failed to connect')]), 'connection_failed'],
    'unexpected client exception' => [fn () => Http::fake([REG_CHECK_URL => fn () => throw new InvalidArgumentException('opção recusada pelo cliente HTTP')]), 'connection_failed'],
    'invalid json' => [fn () => regCheckFake('<html>ok</html>'), 'invalid_json'],
    'empty body' => [fn () => regCheckFake(''), 'invalid_json'],
    'json scalar' => [fn () => regCheckFake('true'), 'invalid_response'],
    'registered missing' => [fn () => regCheckFake(['status' => 'ok']), 'invalid_response'],
    'registered as string' => [fn () => regCheckFake(['registered' => 'true', 'registered_at' => '2026-09-27T14:30:00Z']), 'invalid_response'],
    'registered as 0' => [fn () => regCheckFake(['registered' => 0]), 'invalid_response'],
    'registered_at missing' => [fn () => regCheckFake(['registered' => true]), 'invalid_registered_at'],
    'registered_at null' => [fn () => regCheckFake(['registered' => true, 'registered_at' => null]), 'invalid_registered_at'],
    'registered_at garbage' => [fn () => regCheckFake(['registered' => true, 'registered_at' => 'ontem']), 'invalid_registered_at'],
    'registered_at without timezone' => [fn () => regCheckFake(['registered' => true, 'registered_at' => '2026-09-27T14:30:00']), 'invalid_registered_at'],
    'registered_at date only' => [fn () => regCheckFake(['registered' => true, 'registered_at' => '2026-09-27']), 'invalid_registered_at'],
    'registered_at in the future' => [fn () => regCheckFake(['registered' => true, 'registered_at' => '2026-09-28T16:00:00Z']), 'invalid_registered_at'],
    'registered_at impossible date' => [fn () => regCheckFake(['registered' => true, 'registered_at' => '2026-02-31T10:00:00Z']), 'invalid_registered_at'],
]);

// H — configuração insuficiente

test('H: a product without enough configuration makes no call and starts no trial', function (array $attributes, array $leadAttributes, string $error) {
    Http::fake();
    $lead = regCheckLead(regCheckProduct($attributes), $leadAttributes);
    $before = regCheckCommercial($lead);

    $result = regCheckRun($lead);

    expect($result->status)->toBe('error')
        ->and($result->error)->toBe($error)
        ->and(regCheckCommercial($lead))->toBe($before);

    Http::assertNothingSent();
})->with([
    'no url' => [['registration_api_url' => null], [], 'not_configured'],
    'no lookup field' => [['registration_lookup_field' => null], [], 'not_configured'],
    'unknown lookup field' => [['registration_lookup_field' => 'cpf'], [], 'not_configured'],
    'no token' => [['registration_api_token' => null], [], 'not_configured'],
    'lead without whatsapp' => [[], ['whatsapp' => null], 'missing_identifier'],
    'invalid whatsapp' => [[], ['whatsapp' => '123'], 'missing_identifier'],
    'lead without email' => [['registration_lookup_field' => 'email'], ['email' => null], 'missing_identifier'],
    'invalid email' => [['registration_lookup_field' => 'email'], ['email' => 'nao-e-email'], 'missing_identifier'],
]);

test('H: leads outside the check are never queried', function (array $productAttributes, array $leadAttributes) {
    Http::fake();
    $lead = regCheckLead(regCheckProduct($productAttributes), $leadAttributes);

    expect(regCheckRun($lead))->toBeNull()
        ->and($lead->fresh()->registration_checked_at)->toBeNull();

    // Nem chega a ser selecionado pelo comando (a guarda do checker é a segunda barreira).
    $this->artisan('leads:check-registrations')
        ->expectsOutputToContain('Leads verificados: 0. Cadastrados: 0 (testes iniciados: 0). Não cadastrados: 0. Erros: 0. Ignorados: 0.')
        ->assertSuccessful();
    $this->artisan('leads:check-registrations', ['--lead' => $lead->id])->assertSuccessful();

    Http::assertNothingSent();
})->with([
    'inactive product' => [['active' => false], []],
    'product without trial days' => [['trial_days' => null], []],
    'trial already started' => [[], ['trial_started_at' => '2026-09-20T10:00:00Z', 'trial_ends_at' => '2026-10-04T10:00:00Z']],
]);

// I — lead encerrado

test('I: a converted or lost lead is never queried and never starts a trial', function (string $status) {
    regCheckFake(['registered' => true, 'registered_at' => '2026-09-27T14:30:00Z']);
    $lead = regCheckLead(null, ['status' => $status, 'lost_reason' => $status === 'lost' ? 'no_interest' : null]);
    $before = regCheckCommercial($lead);

    expect(regCheckRun($lead))->toBeNull();
    $this->artisan('leads:check-registrations')->assertSuccessful();

    expect(regCheckCommercial($lead))->toBe($before);
    Http::assertNothingSent();
})->with(['converted', 'lost']);

test('I: a lead closed while the API answered does not start the trial', function () {
    $lead = regCheckLead();
    Http::fake([REG_CHECK_URL => function () use ($lead) {
        DB::table('leads')->where('id', $lead->id)->update(['status' => 'converted']);

        return Http::response(['registered' => true, 'registered_at' => '2026-09-27T14:30:00Z']);
    }]);

    $result = regCheckRun($lead);

    expect($result->status)->toBe('registered')
        ->and($result->trialStarted)->toBeFalse()
        ->and($lead->fresh()->trial_started_at)->toBeNull()
        ->and(LeadActivity::query()->where('lead_id', $lead->id)->count())->toBe(0);
});

// L — credencial

test('L: the API token is stored encrypted and never reaches the screens', function () {
    $product = regCheckProduct();
    $lead = regCheckLead($product);
    $admin = User::factory()->create();

    expect(DB::table('prospect_products')->where('id', $product->id)->value('registration_api_token'))
        ->not->toBe(REG_CHECK_TOKEN)
        ->not->toContain(REG_CHECK_TOKEN)
        ->and($product->fresh()->registration_api_token)->toBe(REG_CHECK_TOKEN)
        ->and(json_encode($product->fresh()))->not->toContain(REG_CHECK_TOKEN);

    $index = $this->actingAs($admin)->get(route('prospect-products.index'));
    $index->assertInertia(fn (Assert $page) => $page
        ->where('products.0.registration_check_mode', 'api')
        ->where('products.0.registration_api_url', REG_CHECK_URL)
        ->where('products.0.registration_lookup_field', 'whatsapp')
        ->where('products.0.registration_api_token_set', true)
        ->missing('products.0.registration_api_token'));
    expect($index->getContent())->not->toContain(REG_CHECK_TOKEN);

    $edit = $this->actingAs($admin)->get(route('leads.edit', $lead));
    $edit->assertInertia(fn (Assert $page) => $page
        ->missing('lead.prospect_product.registration_api_token')
        ->where('registrationCheck.configured', true));
    expect($edit->getContent())->not->toContain(REG_CHECK_TOKEN);
});

// M — SSRF / URL

test('M: unsafe API urls are rejected when the product is saved', function (string $url) {
    $this->actingAs(User::factory()->create())
        ->post(route('prospect-products.store'), [
            'name' => 'Produto',
            'trial_days' => 7,
            'registration_check_mode' => 'api',
            'registration_api_url' => $url,
            'registration_lookup_field' => 'email',
            'registration_api_token' => 'x',
        ])
        ->assertSessionHasErrors('registration_api_url');

    expect(ProspectProduct::query()->count())->toBe(0);
})->with([
    'http' => 'http://registro.produto.example.com/api',
    'ftp' => 'ftp://registro.produto.example.com/api',
    'no scheme' => 'registro.produto.example.com/api',
    'localhost' => 'https://localhost/api',
    'dot localhost' => 'https://app.localhost/api',
    'single label' => 'https://mysql/api',
    'docker service' => 'https://abrasilsistema/api',
    'loopback' => 'https://127.0.0.1/api',
    'private 10' => 'https://10.0.0.5/api',
    'private 172' => 'https://172.18.0.4/api',
    'private 192' => 'https://192.168.1.10/api',
    'metadata' => 'https://169.254.169.254/latest/meta-data',
    'cgnat' => 'https://100.64.1.1/api',
    'zero' => 'https://0.0.0.0/api',
    'ipv6 loopback' => 'https://[::1]/api',
    'credentials' => 'https://user:pass@registro.produto.example.com/api',
    'other port' => 'https://registro.produto.example.com:8443/api',
    'internal suffix' => 'https://n8n.internal/api',
    'fragment' => 'https://registro.produto.example.com/api#x',
]);

test('M: a host resolving to an internal address is blocked before any request', function (array $addresses) {
    Http::fake();
    regCheckResolveTo($addresses);
    $lead = regCheckLead();
    $before = regCheckCommercial($lead);

    $result = regCheckRun($lead);

    expect($result->error)->toBe('blocked_url')
        ->and(regCheckCommercial($lead))->toBe($before);
    Http::assertNothingSent();
})->with([
    'loopback' => [['127.0.0.1']],
    'docker network' => [['172.18.0.7']],
    'metadata' => [['169.254.169.254']],
    'public and private' => [[REG_CHECK_PUBLIC_IP, '10.0.0.1']],
    'does not resolve' => [[]],
]);

test('M: a url saved before the rules (or edited in the database) is still checked at call time', function () {
    Http::fake();
    $lead = regCheckLead(regCheckProduct(['registration_api_url' => 'http://10.0.0.5:8080/api']));

    expect(regCheckRun($lead)->error)->toBe('blocked_url');
    Http::assertNothingSent();
});

test('M: the url guard pins a public address', function () {
    $urls = app(RegistrationApiUrl::class);

    expect($urls->pinnedAddress(REG_CHECK_URL))->toBe(REG_CHECK_PUBLIC_IP)
        ->and(RegistrationApiUrl::formatError(REG_CHECK_URL))->toBeNull()
        ->and(RegistrationApiUrl::formatError('https://registro.produto.example.com:443/api?x=1'))->toBeNull()
        ->and(RegistrationApiUrl::formatError('https://'.REG_CHECK_PUBLIC_IP.'/api'))->toBeNull();
});

// N — isolamento

test('N: two leads and two products do not contaminate each other', function () {
    $urlB = 'https://outro.produto.example.org/check';
    Http::fake([
        REG_CHECK_URL => Http::response(['registered' => true, 'registered_at' => '2026-09-27T14:30:00Z']),
        $urlB => Http::response(['registered' => false]),
    ]);
    $productA = regCheckProduct();
    $productB = regCheckProduct(['name' => 'Produto B', 'trial_days' => 30, 'registration_api_url' => $urlB, 'registration_lookup_field' => 'email', 'registration_api_token' => 'token-b']);
    $leadA = regCheckLead($productA);
    $leadB = regCheckLead($productB, ['whatsapp' => '5551995179173']);

    $this->artisan('leads:check-registrations')->assertSuccessful();

    $leadA->refresh();
    $leadB->refresh();
    expect($leadA->trial_ends_at->toDateTimeString())->toBe('2026-10-11 14:30:00')
        ->and($leadA->registration_check_status)->toBe('registered')
        ->and($leadB->trial_started_at)->toBeNull()
        ->and($leadB->registration_check_status)->toBe('not_registered')
        ->and(LeadActivity::query()->where('lead_id', $leadB->id)->count())->toBe(0);

    Http::assertSent(fn (Request $request) => $request->url() === $urlB
        && $request->hasHeader('Authorization', 'Bearer token-b')
        && $request['lookup_field'] === 'email');
    Http::assertSent(fn (Request $request) => $request->url() === REG_CHECK_URL
        && $request->hasHeader('Authorization', 'Bearer '.REG_CHECK_TOKEN));
    Http::assertSentCount(2);
});

// Comando / agendamento

test('the command checks each lead at most once per interval, never checked first', function () {
    config(['services.prospect_registration_check.interval_minutes' => 360]);
    regCheckFake(['registered' => false]);
    $recent = regCheckLead(null, ['company_name' => 'Recente']);
    $old = regCheckLead(null, ['company_name' => 'Antigo']);
    $never = regCheckLead(null, ['company_name' => 'Nunca']);
    DB::table('leads')->where('id', $recent->id)->update(['registration_checked_at' => '2026-09-28 12:00:00']);
    DB::table('leads')->where('id', $old->id)->update(['registration_checked_at' => '2026-09-28 08:00:00']);

    $this->artisan('leads:check-registrations', ['--limit' => 1])->assertSuccessful();
    expect($never->fresh()->registration_checked_at->toDateTimeString())->toBe('2026-09-28 15:00:00')
        ->and($old->fresh()->registration_checked_at->toDateTimeString())->toBe('2026-09-28 08:00:00');

    $this->artisan('leads:check-registrations')->assertSuccessful();
    expect($old->fresh()->registration_checked_at->toDateTimeString())->toBe('2026-09-28 15:00:00')
        ->and($recent->fresh()->registration_checked_at->toDateTimeString())->toBe('2026-09-28 12:00:00');

    Http::assertSentCount(2);

    // --lead ignora o intervalo.
    $this->artisan('leads:check-registrations', ['--lead' => $recent->id])->assertSuccessful();
    Http::assertSentCount(3);
});

test('a 429 stops the checks of that product for the rest of the run', function () {
    $urlB = 'https://outro.produto.example.org/check';
    Http::fake([REG_CHECK_URL => Http::response('', 429), $urlB => Http::response(['registered' => false])]);
    $product = regCheckProduct();
    regCheckLead($product);
    regCheckLead($product);
    regCheckLead(regCheckProduct(['registration_api_url' => $urlB]));

    $this->artisan('leads:check-registrations')
        ->expectsOutputToContain('Não cadastrados: 1. Erros: 1. Ignorados: 1.')
        ->assertSuccessful();

    Http::assertSentCount(2);
});

test('the schedule is not registered unless explicitly enabled', function () {
    $scheduled = fn () => collect(app(Schedule::class)->events())
        ->contains(fn (Event $event) => str_contains((string) $event->command, 'leads:check-registrations'));

    expect(config('services.prospect_registration_check.enabled'))->toBeFalse()
        ->and($scheduled())->toBeFalse();

    config(['services.prospect_registration_check.enabled' => true]);
    require base_path('routes/console.php');

    $event = collect(app(Schedule::class)->events())
        ->first(fn (Event $event) => str_contains((string) $event->command, 'leads:check-registrations'));
    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 * * * *')
        ->and($event->withoutOverlapping)->toBeTrue();
});

// Tela do lead

test('the lead page shows the check state and the manual check starts the trial', function () {
    regCheckFake(['registered' => true, 'registered_at' => '2026-09-27T14:30:00Z']);
    $lead = regCheckLead();
    $admin = User::factory()->create();

    $this->actingAs($admin)->get(route('leads.edit', $lead))
        ->assertInertia(fn (Assert $page) => $page
            ->where('registrationCheck', [
                'mode' => 'api',
                'configured' => true,
                'lookup_field' => 'whatsapp',
                'status' => null,
                'status_label' => 'Ainda não verificado',
                'error' => null,
                'error_label' => null,
                'checked_at' => null,
                'can_check' => true,
            ])
            ->where('trialPeriod.can_start', true));

    $this->actingAs($admin)
        ->from(route('leads.edit', $lead))
        ->post(route('leads.registration-check', $lead))
        ->assertRedirect(route('leads.edit', $lead))
        ->assertInertiaFlash('toast.type', 'success');

    $this->actingAs($admin)->get(route('leads.edit', $lead))
        ->assertInertia(fn (Assert $page) => $page
            ->where('registrationCheck.status', 'registered')
            ->where('registrationCheck.checked_at', '2026-09-28T15:00:00+00:00')
            ->where('registrationCheck.can_check', false)
            ->where('trialPeriod.status', 'active')
            ->where('trialPeriod.started_at', '2026-09-27T14:30:00+00:00'));
});

test('the manual check reports an API error without changing the lead', function () {
    regCheckFake('', 500);
    $lead = regCheckLead();
    $before = regCheckCommercial($lead);

    $this->actingAs(User::factory()->create())
        ->from(route('leads.edit', $lead))
        ->post(route('leads.registration-check', $lead))
        ->assertInertiaFlash('toast.type', 'error')
        ->assertInertiaFlash('toast.message', 'Não foi possível verificar o cadastro: Erro no servidor da API (5xx). Nada foi alterado.');

    expect(regCheckCommercial($lead))->toBe($before);
});

test('the manual trial start still works for an API product', function () {
    Http::fake();
    $lead = regCheckLead();

    $this->actingAs(User::factory()->create())->post(route('leads.start-trial', $lead))->assertRedirect();

    expect($lead->fresh()->trial_started_at->toDateTimeString())->toBe('2026-09-28 15:00:00');
    Http::assertNothingSent();
});

test('only admins can run the manual check', function () {
    Http::fake();
    $lead = regCheckLead();

    $this->actingAs(User::factory()->reader()->create())
        ->post(route('leads.registration-check', $lead))
        ->assertForbidden();

    Http::assertNothingSent();
});

// Cadastro do produto

test('products default to manual and save the API configuration', function () {
    $admin = User::factory()->create();

    $this->actingAs($admin)->post(route('prospect-products.store'), ['name' => 'Manual', 'trial_days' => 7])->assertSessionHasNoErrors();
    expect(ProspectProduct::query()->sole()->registration_check_mode)->toBe('manual');

    $this->actingAs($admin)->post(route('prospect-products.store'), [
        'name' => 'API',
        'trial_days' => 7,
        'registration_check_mode' => 'api',
        'registration_api_url' => ' '.REG_CHECK_URL.' ',
        'registration_lookup_field' => 'email',
        'registration_api_token' => REG_CHECK_TOKEN,
    ])->assertSessionHasNoErrors();

    $product = ProspectProduct::query()->where('name', 'API')->sole();
    expect($product->registration_check_mode)->toBe('api')
        ->and($product->registration_api_url)->toBe(REG_CHECK_URL)
        ->and($product->registration_lookup_field)->toBe('email')
        ->and($product->registration_api_token)->toBe(REG_CHECK_TOKEN)
        ->and($product->hasRegistrationApiConfigured())->toBeTrue();
});

test('the API mode requires url, lookup field and token', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('prospect-products.store'), ['name' => 'API', 'registration_check_mode' => 'api'])
        ->assertSessionHasErrors(['registration_api_url', 'registration_lookup_field', 'registration_api_token']);

    $this->actingAs(User::factory()->create())
        ->post(route('prospect-products.store'), ['name' => 'X', 'registration_check_mode' => 'webhook', 'registration_lookup_field' => 'cpf'])
        ->assertSessionHasErrors(['registration_check_mode', 'registration_lookup_field']);
});

test('editing keeps the saved token when blank, replaces it when typed and removes it on request', function () {
    $admin = User::factory()->create();
    $product = regCheckProduct();
    $payload = ['name' => 'Produto API', 'trial_days' => 14, 'registration_check_mode' => 'api', 'registration_api_url' => REG_CHECK_URL, 'registration_lookup_field' => 'whatsapp'];

    $this->actingAs($admin)->put(route('prospect-products.update', $product), [...$payload, 'registration_api_token' => ''])->assertSessionHasNoErrors();
    expect($product->fresh()->registration_api_token)->toBe(REG_CHECK_TOKEN);

    $this->actingAs($admin)->put(route('prospect-products.update', $product), [...$payload, 'registration_api_token' => 'novo-token'])->assertSessionHasNoErrors();
    expect($product->fresh()->registration_api_token)->toBe('novo-token');

    // Remover o token sem outro, ainda em modo API: recusado (a API precisa de token).
    $this->actingAs($admin)->put(route('prospect-products.update', $product), [...$payload, 'registration_api_token_clear' => true])->assertSessionHasErrors('registration_api_token');
    expect($product->fresh()->registration_api_token)->toBe('novo-token');

    $this->actingAs($admin)->put(route('prospect-products.update', $product), [...$payload, 'registration_check_mode' => 'manual', 'registration_api_token_clear' => true])->assertSessionHasNoErrors();
    expect($product->fresh()->registration_api_token)->toBeNull()
        ->and($product->fresh()->registration_check_mode)->toBe('manual');
});

// FASE 10 — compatibilidade com trial_expired

test('a trial started by the API enters the existing trial_expired flow unchanged', function () {
    Setting::query()->create(['key' => Setting::TRIAL_EXPIRED_AUTOMATION_SINCE, 'value' => '2026-09-01T00:00:00Z']);
    regCheckFake(['registered' => true, 'registered_at' => '2026-09-27T14:30:00Z']);
    $lead = regCheckLead(regCheckProduct(['trial_days' => 7, 'trial_expiration_text' => 'Seu teste terminou.']));
    $headers = ['Authorization' => 'Bearer '.config('services.ab_prospect.token')];

    regCheckRun($lead);

    $this->getJson(route('api.prospects.automation-state', $lead), $headers)
        ->assertJsonPath('trial', ['state' => 'active', 'started_at' => '2026-09-27', 'ends_at' => '2026-10-04'])
        ->assertJsonPath('automations.trial_expired.eligible', false);

    // Segunda, 05/10/2026 12:00 em São Paulo: vencido (04/10 11:30) e dentro do horário.
    $this->travelTo(now('UTC')->setDate(2026, 10, 5)->setTime(15, 0, 0));

    $this->getJson(route('api.prospects.automation-state', $lead), $headers)
        ->assertJsonPath('trial.state', 'expired')
        ->assertJsonPath('automations.trial_expired', ['eligible' => true, 'automation_key' => 'trial_expired:2026-10-04', 'message' => 'Seu teste terminou.', 'last_execution' => null]);

    $this->getJson(route('api.prospects.automation-candidates.trial-expired'), $headers)
        ->assertJsonPath('data.0.lead_id', $lead->id)
        ->assertJsonPath('data.0.automation_key', 'trial_expired:2026-10-04');
});

// CRM-WA-30.2 (alternativa 4) — cadastro antigo descoberto já vencido não entra na automação trial_expired

test('a registration discovered after the trial had already expired is never an automation candidate', function () {
    Setting::query()->create(['key' => Setting::TRIAL_EXPIRED_AUTOMATION_SINCE, 'value' => '2026-09-01T00:00:00Z']);
    // Cadastro em 10/09 com 14 dias: venceu em 24/09, depois do corte, e só é descoberto em 28/09.
    regCheckFake(['registered' => true, 'registered_at' => '2026-09-10T14:30:00Z']);
    $lead = regCheckLead(regCheckProduct(['trial_expiration_text' => 'Seu teste terminou.']));
    $headers = ['Authorization' => 'Bearer '.config('services.ab_prospect.token')];

    expect(regCheckRun($lead)->trialStarted)->toBeTrue();

    $this->getJson(route('api.prospects.automation-state', $lead), $headers)
        ->assertJsonPath('trial.state', 'expired')
        ->assertJsonPath('automations.trial_expired.eligible', false)
        ->assertJsonPath('automations.trial_expired.automation_key', 'trial_expired:2026-09-24');
    $this->getJson(route('api.prospects.automation-candidates.trial-expired'), $headers)->assertExactJson(['data' => []]);

    // Nem depois: a descoberta não muda, então o lead nunca vira candidato sozinho.
    $this->travelTo(now('UTC')->setDate(2026, 10, 5)->setTime(15, 0, 0));
    $this->getJson(route('api.prospects.automation-candidates.trial-expired'), $headers)->assertExactJson(['data' => []]);
});

test('a registration discovered with the trial expiring at that very instant is not a candidate', function () {
    Setting::query()->create(['key' => Setting::TRIAL_EXPIRED_AUTOMATION_SINCE, 'value' => '2026-09-01T00:00:00Z']);
    regCheckFake(['registered' => true, 'registered_at' => '2026-09-14T15:00:00Z']);
    $lead = regCheckLead(regCheckProduct(['trial_expiration_text' => 'Seu teste terminou.']));

    regCheckRun($lead);

    $lead->refresh();
    expect($lead->trial_ends_at->toDateTimeString())->toBe('2026-09-28 15:00:00')
        ->and($lead->registration_checked_at->toDateTimeString())->toBe('2026-09-28 15:00:00')
        ->and($lead->isTrialExpiredAutomationCandidate())->toBeFalse();
});
