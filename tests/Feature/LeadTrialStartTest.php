<?php

use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\ProspectProduct;
use App\Models\Setting;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * CRM-WA-23: "Iniciar período de teste". Início = agora, vencimento = agora + trial_days do
 * produto vinculado, gravados uma única vez. A situação na tela é calculada das datas.
 */

beforeEach(function () {
    // 28/09/2026 17:00 em São Paulo (20:00 UTC).
    $this->travelTo(now('UTC')->setDate(2026, 9, 28)->setTime(20, 0, 0));
});

/** @param  array<string, mixed>  $attributes */
function trialStartProduct(?int $trialDays = 14, array $attributes = []): ProspectProduct
{
    return ProspectProduct::query()->create(['name' => 'VetorOS', 'trial_days' => $trialDays, ...$attributes]);
}

/** @param  array<string, mixed>  $attributes */
function trialStartLead(?ProspectProduct $product, array $attributes = []): Lead
{
    return Lead::query()->create([
        'company_name' => 'Lead trial '.uniqid(),
        'status' => 'interested',
        ...($product ? ['prospect_product_id' => $product->id] : ['product' => 'outro']),
        ...$attributes,
    ]);
}

function trialStartPost(Lead $lead, ?User $user = null): TestResponse
{
    return test()->actingAs($user ?? User::factory()->create())
        ->from(route('leads.edit', $lead))
        ->post(route('leads.start-trial', $lead));
}

/** @return array{0: ?string, 1: ?string} */
function trialStartRaw(Lead $lead): array
{
    $row = DB::table('leads')->where('id', $lead->id)->first(['trial_started_at', 'trial_ends_at']);

    return [$row->trial_started_at, $row->trial_ends_at];
}

function trialStartNotes(Lead $lead)
{
    return LeadActivity::query()->where('lead_id', $lead->id)->where('description', 'like', 'Período de teste%')->get();
}

test('the trial columns are nullable datetimes', function () {
    $columns = collect(Schema::getColumns('leads'))->keyBy('name');

    foreach (['trial_started_at', 'trial_ends_at'] as $column) {
        expect($columns[$column]['nullable'])->toBeTrue()
            ->and($columns[$column]['type_name'])->toBe('datetime');
    }
});

test('the trial dates are cast to datetimes that keep the time', function () {
    $lead = trialStartLead(null, ['trial_started_at' => now(), 'trial_ends_at' => now()->addDays(3)])->fresh();

    expect($lead->trial_started_at)->toBeInstanceOf(CarbonInterface::class)
        ->and($lead->trial_ends_at)->toBeInstanceOf(CarbonInterface::class)
        ->and($lead->trial_started_at->toDateTimeString())->toBe('2026-09-28 20:00:00')
        ->and($lead->trial_ends_at->toDateTimeString())->toBe('2026-10-01 20:00:00');
});

test('starting the trial records now and now plus the product trial days', function () {
    $user = User::factory()->create();
    $lead = trialStartLead(trialStartProduct(14));

    trialStartPost($lead, $user)
        ->assertRedirect(route('leads.edit', $lead))
        ->assertInertiaFlash('toast.type', 'success')
        ->assertInertiaFlash('toast.message', 'Período de teste iniciado.');

    // 17:00 em São Paulo → vence em 12/10 às 17:00 (20:00 UTC).
    expect(trialStartRaw($lead))->toBe(['2026-09-28 20:00:00', '2026-10-12 20:00:00']);

    $lead->refresh();

    expect($lead->trial_started_at->diffInSeconds($lead->trial_ends_at))->toEqual(14 * 86400)
        ->and($lead->trialPeriodStatus())->toBe('active')
        ->and($lead->trial_state)->toBe('active');
});

test('both dates come from the same instant', function () {
    // Sub-segundo no relógio: início e vencimento usam o mesmo "agora", truncado no segundo.
    $this->travelTo(now('UTC')->setDate(2026, 9, 28)->setTime(20, 0, 0, 999999));
    $lead = trialStartLead(trialStartProduct(1));

    expect($lead->startTrial(User::factory()->create()))->toBeTrue()
        ->and(trialStartRaw($lead))->toBe(['2026-09-28 20:00:00', '2026-09-29 20:00:00']);
});

test('trial_days = 0 starts a trial that expires at the same instant', function () {
    $lead = trialStartLead(trialStartProduct(0));

    trialStartPost($lead)->assertInertiaFlash('toast.type', 'success');

    expect(trialStartRaw($lead))->toBe(['2026-09-28 20:00:00', '2026-09-28 20:00:00'])
        ->and($lead->fresh()->trialPeriodStatus())->toBe('expired')
        ->and(trialStartNotes($lead)->sole()->description)->toBe('Período de teste iniciado (VetorOS, 0 dias). Vencimento: 28/09/2026 17:00.');
});

test('the trial cannot start without a trial period configured', function (bool $withProduct, string $message) {
    $lead = trialStartLead($withProduct ? trialStartProduct(null) : null);

    expect($lead->trialPeriod())->toMatchArray(['status' => 'none', 'status_label' => 'Sem teste', 'can_start' => false]);

    trialStartPost($lead)
        ->assertRedirect(route('leads.edit', $lead))
        ->assertInertiaFlash('toast.type', 'error')
        ->assertInertiaFlash('toast.message', $message);

    expect(trialStartRaw($lead))->toBe([null, null])
        ->and(trialStartNotes($lead))->toBeEmpty()
        ->and($lead->fresh()->startTrial(User::factory()->create()))->toBeFalse()
        ->and(trialStartRaw($lead))->toBe([null, null]);
})->with([
    'produto com trial_days NULL' => [true, 'O produto/serviço deste lead não tem período de teste.'],
    'lead sem produto' => [false, 'Vincule o lead a um produto/serviço antes de iniciar o teste.'],
]);

test('an inactive product already linked still starts the trial', function () {
    $product = trialStartProduct(14);
    $lead = trialStartLead($product);
    $product->update(['active' => false]);

    expect($lead->fresh()->trialPeriod()['can_start'])->toBeTrue();

    trialStartPost($lead)->assertInertiaFlash('toast.type', 'success');

    expect(trialStartRaw($lead))->toBe(['2026-09-28 20:00:00', '2026-10-12 20:00:00']);
});

test('a second start does not change the dates', function () {
    $product = trialStartProduct(14);
    $lead = trialStartLead($product);
    trialStartPost($lead)->assertInertiaFlash('toast.type', 'success');

    $this->travelTo(now()->addHours(5));
    $product->update(['trial_days' => 30]);

    trialStartPost($lead)
        ->assertInertiaFlash('toast.type', 'error')
        ->assertInertiaFlash('toast.message', 'O período de teste deste lead já foi iniciado.');

    expect(trialStartRaw($lead))->toBe(['2026-09-28 20:00:00', '2026-10-12 20:00:00'])
        ->and(trialStartNotes($lead))->toHaveCount(1)
        ->and($lead->fresh()->trialPeriod()['can_start'])->toBeFalse();
});

test('concurrent starts record the trial only once', function () {
    $user = User::factory()->create();
    $lead = trialStartLead(trialStartProduct(14));

    // Duas requisições carregaram o lead antes de qualquer uma gravar (clique duplo).
    $first = Lead::query()->findOrFail($lead->id);
    $second = Lead::query()->findOrFail($lead->id);

    expect($first->trialStartBlocker())->toBeNull()
        ->and($second->trialStartBlocker())->toBeNull()
        ->and($first->startTrial($user))->toBeTrue();

    $this->travelTo(now()->addSeconds(2));

    // A segunda ainda vê o teste como não iniciado, mas o UPDATE condicional não altera nada.
    expect($second->trial_started_at)->toBeNull()
        ->and($second->startTrial($user))->toBeFalse()
        ->and(trialStartRaw($lead))->toBe(['2026-09-28 20:00:00', '2026-10-12 20:00:00'])
        ->and(trialStartNotes($lead))->toHaveCount(1);
});

test('a product change between loading and starting does not start the trial', function () {
    $lead = trialStartLead(trialStartProduct(14));
    $stale = Lead::query()->findOrFail($lead->id);
    $stale->load('prospectProduct');

    // Outra tela trocou o produto do lead para um sem período de teste.
    $lead->update(['prospect_product_id' => ProspectProduct::query()->create(['name' => 'Site institucional'])->id]);

    expect($stale->startTrial(User::factory()->create()))->toBeFalse()
        ->and(trialStartRaw($lead))->toBe([null, null]);
});

test('changing the product trial days later keeps the started trial', function () {
    $product = trialStartProduct(14);
    $lead = trialStartLead($product);
    trialStartPost($lead);

    $product->update(['trial_days' => 30]);
    $this->actingAs(User::factory()->create())->get(route('leads.edit', $lead))->assertOk();

    expect(trialStartRaw($lead))->toBe(['2026-09-28 20:00:00', '2026-10-12 20:00:00']);
});

test('switching the lead to another product keeps the started trial', function () {
    $user = User::factory()->create();
    $lead = trialStartLead(trialStartProduct(14), ['user_id' => $user->id]);
    trialStartPost($lead, $user);
    $vetorpet = ProspectProduct::query()->create(['name' => 'VetorPet', 'trial_days' => 30]);

    $this->actingAs($user)
        ->put(route('leads.update', $lead), [
            'company_name' => $lead->company_name,
            'prospect_product_id' => $vetorpet->id,
            'status' => 'interested',
            'user_id' => $user->id,
        ])
        ->assertSessionHasNoErrors();

    $lead->refresh();

    expect($lead->prospect_product_id)->toBe($vetorpet->id)
        ->and(trialStartRaw($lead))->toBe(['2026-09-28 20:00:00', '2026-10-12 20:00:00'])
        ->and($lead->trialPeriod())->toMatchArray(['product_name' => 'VetorPet', 'status' => 'active', 'can_start' => false]);
});

test('the lead form cannot set or change the trial dates', function () {
    $user = User::factory()->create();
    $lead = trialStartLead(trialStartProduct(14), ['user_id' => $user->id]);
    $update = fn () => $this->actingAs($user)
        ->put(route('leads.update', $lead), [
            'company_name' => $lead->company_name,
            'prospect_product_id' => $lead->prospect_product_id,
            'status' => 'interested',
            'user_id' => $user->id,
            'trial_started_at' => '2026-01-01',
            'trial_ends_at' => '2026-12-31',
        ])
        ->assertSessionHasNoErrors();

    $update();
    expect(trialStartRaw($lead))->toBe([null, null]);

    trialStartPost($lead, $user);
    $update();
    expect(trialStartRaw($lead))->toBe(['2026-09-28 20:00:00', '2026-10-12 20:00:00']);
});

test('a trial period longer than the database supports is refused', function () {
    $lead = trialStartLead(trialStartProduct(4294967295));

    expect($lead->trialPeriod()['can_start'])->toBeFalse();

    trialStartPost($lead)
        ->assertInertiaFlash('toast.type', 'error')
        ->assertInertiaFlash('toast.message', 'O prazo de teste do produto/serviço é longo demais.');

    expect(trialStartRaw($lead))->toBe([null, null]);
});

test('status: none and not started', function () {
    expect(trialStartLead(null)->trialPeriodStatus())->toBe('none')
        ->and(trialStartLead(trialStartProduct(null))->trialPeriodStatus())->toBe('none')
        ->and(trialStartLead(ProspectProduct::query()->create(['name' => 'VetorPet', 'trial_days' => 14]))->trialPeriod())
        ->toMatchArray(['status' => 'not_started', 'status_label' => 'Não iniciado', 'trial_days' => 14, 'can_start' => true]);
});

test('status: active, ends today and expired follow the São Paulo day and the expiry instant', function () {
    $lead = trialStartLead(trialStartProduct(14));
    trialStartPost($lead);
    $status = function (int $month, int $day, int $utcHour, int $minute = 0, int $second = 0) use ($lead): string {
        $this->travelTo(now('UTC')->setDate(2026, $month, $day)->setTime($utcHour, $minute, $second));

        return $lead->fresh()->trialPeriodStatus();
    };

    expect($status(10, 5, 15))->toBe('active')
        // 11/10 23:59 em São Paulo (12/10 02:59 UTC): ainda não é o dia do vencimento.
        ->and($status(10, 12, 2, 59))->toBe('active')
        // 12/10 00:00 em São Paulo: vence hoje.
        ->and($status(10, 12, 3))->toBe('today')
        ->and($status(10, 12, 19, 59, 59))->toBe('today')
        // 12/10 17:00 em São Paulo: vencimento alcançado.
        ->and($status(10, 12, 20))->toBe('expired')
        ->and($status(11, 30, 12))->toBe('expired')
        ->and(Lead::TRIAL_PERIOD_STATUSES['expired'])->toBe('Expirado')
        ->and(Lead::TRIAL_PERIOD_STATUSES['today'])->toBe('Vence hoje');
});

test('a started trial without an end is active', function () {
    $lead = trialStartLead(trialStartProduct(14), ['trial_started_at' => now()->subDays(40)]);

    expect($lead->fresh()->trialPeriodStatus())->toBe('active');
});

test('expiring does not change the lead or its history', function () {
    $user = User::factory()->create();
    $lead = trialStartLead(trialStartProduct(14), [
        'user_id' => $user->id,
        'status' => 'meeting',
        'next_follow_up_at' => '2026-10-01 10:00',
    ]);
    trialStartPost($lead, $user);
    $before = (array) DB::table('leads')->where('id', $lead->id)->first();
    $activities = LeadActivity::query()->where('lead_id', $lead->id)->count();

    $this->travelTo(now()->addDays(20));

    $this->actingAs($user)
        ->get(route('leads.edit', $lead))
        ->assertInertia(fn (Assert $page) => $page->where('trialPeriod.status', 'expired'));
    $this->actingAs($user)->get(route('leads.index'))->assertOk();
    Lead::query()->trialState('expired')->get();

    expect((array) DB::table('leads')->where('id', $lead->id)->first())->toBe($before)
        ->and(LeadActivity::query()->where('lead_id', $lead->id)->count())->toBe($activities);
});

test('only admins can start a trial', function () {
    $lead = trialStartLead(trialStartProduct(14));

    $this->post(route('leads.start-trial', $lead))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->reader()->create())
        ->post(route('leads.start-trial', $lead))
        ->assertForbidden();

    expect(trialStartRaw($lead))->toBe([null, null])
        ->and(trialStartNotes($lead))->toBeEmpty();
});

test('starting the trial writes a single note to the history', function (int $trialDays, string $description) {
    $user = User::factory()->create();
    $lead = trialStartLead(trialStartProduct($trialDays));

    trialStartPost($lead, $user);

    $note = trialStartNotes($lead)->sole();

    expect($note->description)->toBe($description)
        ->and($note->type)->toBe('note')
        ->and($note->user_id)->toBe($user->id)
        ->and($note->direction)->toBeNull()
        ->and(LeadActivity::query()->where('lead_id', $lead->id)->count())->toBe(1);
})->with([
    '14 dias' => [14, 'Período de teste iniciado (VetorOS, 14 dias). Vencimento: 12/10/2026 17:00.'],
    '1 dia' => [1, 'Período de teste iniciado (VetorOS, 1 dia). Vencimento: 29/09/2026 17:00.'],
]);

test('the edit page receives the trial period', function () {
    $user = User::factory()->create();
    $lead = trialStartLead(trialStartProduct(14));

    $this->actingAs($user)
        ->get(route('leads.edit', $lead))
        ->assertInertia(fn (Assert $page) => $page
            ->where('trialPeriod', [
                'status' => 'not_started',
                'status_label' => 'Não iniciado',
                'product_name' => 'VetorOS',
                'trial_days' => 14,
                'started_at' => null,
                'ends_at' => null,
                'can_start' => true,
                'closed' => false,
            ]));

    trialStartPost($lead, $user);

    $this->actingAs($user)
        ->get(route('leads.edit', $lead))
        ->assertInertia(fn (Assert $page) => $page
            ->where('trialPeriod.status', 'active')
            ->where('trialPeriod.started_at', '2026-09-28T20:00:00+00:00')
            ->where('trialPeriod.ends_at', '2026-10-12T20:00:00+00:00')
            ->where('trialPeriod.can_start', false));
});

test('the automation state keeps its contract for a trial started here', function () {
    // CRM-WA-24: automação ligada e produto com texto de vencimento, para o eligible valer.
    Setting::query()->create(['key' => Setting::TRIAL_EXPIRED_AUTOMATION_SINCE, 'value' => '2026-09-01T00:00:00Z']);
    $lead = trialStartLead(trialStartProduct(14, ['trial_expiration_text' => 'Seu teste terminou.']), ['whatsapp' => '5551995179173']);
    trialStartPost($lead);
    $headers = ['Authorization' => 'Bearer '.config('services.ab_prospect.token')];

    $this->getJson(route('api.prospects.automation-state', $lead), $headers)
        ->assertJsonPath('trial', ['state' => 'active', 'started_at' => '2026-09-28', 'ends_at' => '2026-10-12'])
        ->assertJsonPath('automations.trial_expired', ['eligible' => false, 'automation_key' => null, 'message' => 'Seu teste terminou.']);

    $this->travelTo(now('UTC')->setDate(2026, 10, 12)->setTime(20, 0, 0));

    $this->getJson(route('api.prospects.automation-state', $lead), $headers)
        ->assertJsonPath('trial', ['state' => 'expired', 'started_at' => '2026-09-28', 'ends_at' => '2026-10-12'])
        ->assertJsonPath('automations.trial_expired', ['eligible' => true, 'automation_key' => 'trial_expired:2026-10-12', 'message' => 'Seu teste terminou.']);
});

test('the migration keeps the CRM-WA-16 calendar dates with the same expiry', function () {
    $migration = require database_path('migrations/2026_09_29_100000_change_trial_dates_to_datetime_on_leads_table.php');
    $migration->down();

    $lead = trialStartLead(null);
    $onlyStart = trialStartLead(null);
    DB::table('leads')->where('id', $lead->id)->update(['trial_started_at' => '2026-09-20', 'trial_ends_at' => '2026-10-05']);
    DB::table('leads')->where('id', $onlyStart->id)->update(['trial_started_at' => '2026-09-20']);

    $migration->up();

    // Início 00:00 de São Paulo; término (último dia 05/10) vence às 00:00 de 06/10.
    expect(trialStartRaw($lead))->toBe(['2026-09-20 03:00:00', '2026-10-06 03:00:00'])
        ->and(trialStartRaw($onlyStart))->toBe(['2026-09-20 03:00:00', null])
        ->and($lead->fresh()->trialLastDay())->toBe('2026-10-05');

    $migration->down();

    expect(array_map(fn (?string $value) => $value === null ? null : substr($value, 0, 10), trialStartRaw($lead)))
        ->toBe(['2026-09-20', '2026-10-05']);

    $migration->up();
});

/*
 * CRM-WA-23.1: lead Convertido/Perdido (Lead::CLOSED_STATUSES) não inicia período de teste.
 * Datas de um teste já iniciado ficam como estão; o automation-state não muda.
 */

test('a closed lead cannot start a trial and nothing changes', function (string $status) {
    $user = User::factory()->create();
    $lead = trialStartLead(trialStartProduct(14), [
        'user_id' => $user->id,
        'status' => $status,
        'lost_reason' => $status === 'lost' ? 'price' : null,
    ]);
    $before = (array) DB::table('leads')->where('id', $lead->id)->first();

    expect($lead->fresh()->trialPeriod())->toMatchArray([
        'status' => 'not_started',
        'status_label' => 'Não iniciado',
        'can_start' => false,
        'closed' => true,
    ]);

    trialStartPost($lead, $user)
        ->assertRedirect(route('leads.edit', $lead))
        ->assertInertiaFlash('toast.type', 'error')
        ->assertInertiaFlash('toast.message', 'Lead convertido ou perdido não pode iniciar período de teste.');

    expect($lead->fresh()->startTrial($user))->toBeFalse()
        ->and(trialStartRaw($lead))->toBe([null, null])
        ->and(LeadActivity::query()->where('lead_id', $lead->id)->count())->toBe(0)
        ->and((array) DB::table('leads')->where('id', $lead->id)->first())->toBe($before);
})->with(['converted', 'lost']);

test('the edit page hides the start action for a closed lead', function (string $status) {
    $lead = trialStartLead(trialStartProduct(14), [
        'status' => $status,
        'lost_reason' => $status === 'lost' ? 'price' : null,
    ]);

    // O card só mostra "Iniciar período de teste" com status not_started e closed false.
    $this->actingAs(User::factory()->create())
        ->get(route('leads.edit', $lead))
        ->assertInertia(fn (Assert $page) => $page
            ->where('trialPeriod.status', 'not_started')
            ->where('trialPeriod.trial_days', 14)
            ->where('trialPeriod.can_start', false)
            ->where('trialPeriod.closed', true));
})->with(['converted', 'lost']);

test('an open lead still starts the trial', function (string $status) {
    $lead = trialStartLead(trialStartProduct(14), ['status' => $status]);

    expect($lead->fresh()->trialPeriod())->toMatchArray(['can_start' => true, 'closed' => false]);

    trialStartPost($lead)->assertInertiaFlash('toast.type', 'success');

    expect(trialStartRaw($lead))->toBe(['2026-09-28 20:00:00', '2026-10-12 20:00:00'])
        ->and(trialStartNotes($lead))->toHaveCount(1)
        ->and($lead->fresh()->status)->toBe($status);
})->with(['new', 'contacted', 'interested', 'meeting']);

test('closing a lead keeps its started trial intact', function (string $status) {
    $user = User::factory()->create();
    $lead = trialStartLead(trialStartProduct(14), ['user_id' => $user->id]);
    trialStartPost($lead, $user);

    $this->actingAs($user)
        ->put(route('leads.update', $lead), [
            'company_name' => $lead->company_name,
            'prospect_product_id' => $lead->prospect_product_id,
            'status' => $status,
            'lost_reason' => $status === 'lost' ? 'price' : null,
            'user_id' => $user->id,
        ])
        ->assertSessionHasNoErrors();

    $lead->refresh();

    expect($lead->status)->toBe($status)
        ->and(trialStartRaw($lead))->toBe(['2026-09-28 20:00:00', '2026-10-12 20:00:00'])
        ->and(trialStartNotes($lead))->toHaveCount(1)
        ->and($lead->trialPeriod())->toMatchArray([
            'status' => 'active',
            'started_at' => '2026-09-28T20:00:00+00:00',
            'ends_at' => '2026-10-12T20:00:00+00:00',
            'can_start' => false,
            'closed' => true,
        ]);

    trialStartPost($lead, $user)->assertInertiaFlash('toast.type', 'error');

    expect(trialStartRaw($lead))->toBe(['2026-09-28 20:00:00', '2026-10-12 20:00:00'])
        ->and(trialStartNotes($lead))->toHaveCount(1);
})->with(['converted', 'lost']);

test('a lead closed between loading and starting does not start the trial', function (string $status) {
    $user = User::factory()->create();
    $lead = trialStartLead(trialStartProduct(14));
    $stale = Lead::query()->findOrFail($lead->id);
    $stale->load('prospectProduct');

    expect($stale->trialStartBlocker())->toBeNull();

    // Outra tela converteu/perdeu o lead antes do UPDATE; a instância carregada ainda o vê aberto.
    DB::table('leads')->where('id', $lead->id)->update([
        'status' => $status,
        'lost_reason' => $status === 'lost' ? 'price' : null,
    ]);
    $before = (array) DB::table('leads')->where('id', $lead->id)->first();

    expect($stale->status)->toBe('interested')
        ->and($stale->startTrial($user))->toBeFalse()
        ->and(trialStartRaw($lead))->toBe([null, null])
        ->and(trialStartNotes($lead))->toBeEmpty()
        ->and((array) DB::table('leads')->where('id', $lead->id)->first())->toBe($before)
        ->and($lead->fresh()->trialStartBlocker())->toBe('Lead convertido ou perdido não pode iniciar período de teste.');
})->with(['converted', 'lost']);

test('the automation state treats a closed lead as before', function (string $status) {
    $user = User::factory()->create();
    $lead = trialStartLead(trialStartProduct(14), ['whatsapp' => '5551995179173', 'user_id' => $user->id]);
    trialStartPost($lead, $user);
    $lead->update(['status' => $status, 'lost_reason' => $status === 'lost' ? 'price' : null]);
    $headers = ['Authorization' => 'Bearer '.config('services.ab_prospect.token')];

    // Mesma regra do CRM-WA-23: encerrado é none, com as datas gravadas, e nunca elegível.
    $this->travelTo(now('UTC')->setDate(2026, 10, 12)->setTime(20, 0, 0));

    $this->getJson(route('api.prospects.automation-state', $lead), $headers)
        ->assertOk()
        ->assertJsonPath('is_closed', true)
        ->assertJsonPath('trial', ['state' => 'none', 'started_at' => '2026-09-28', 'ends_at' => '2026-10-12'])
        ->assertJsonPath('automations.trial_expired', ['eligible' => false, 'automation_key' => null, 'message' => null]);
})->with(['converted', 'lost']);
