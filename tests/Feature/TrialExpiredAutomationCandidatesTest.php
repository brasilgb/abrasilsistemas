<?php

use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\LeadAutomationExecution;
use App\Models\ProspectProduct;
use App\Models\Setting;
use App\Services\WhatsappBusinessHours;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;

/*
 * CRM-WA-24: candidatos à mensagem automática de teste vencido. Regra única em
 * Lead::trialExpiredAutomationCandidates, usada pela listagem e pelo eligible do automation-state:
 * automação ligada (corte), horário de atendimento, trial_state expired, produto com
 * trial_expiration_text, WhatsApp válido, sem cliente aguardando resposta e chave não reservada.
 * Horário padrão (sem settings): segunda a sexta, 08:00–18:00 em São Paulo.
 */

const CANDIDATES_TEXT = 'Seu teste do VetorOS terminou. Podemos conversar?';

beforeEach(function () {
    // Segunda, 28/09/2026 12:00 em São Paulo (15:00 UTC): dentro do horário padrão.
    $this->travelTo(now()->setDate(2026, 9, 28)->setTime(15, 0, 0));

    candidatesSince('2026-09-01T00:00:00-03:00');
    $this->product = ProspectProduct::query()->create(['name' => 'VetorOS', 'trial_days' => 7, 'trial_expiration_text' => CANDIDATES_TEXT]);
});

/** @return array<string, string> */
function candidatesToken(): array
{
    return ['Authorization' => 'Bearer '.config('services.ab_prospect.token')];
}

function candidatesSince(?string $value): void
{
    Setting::query()->where('key', Setting::TRIAL_EXPIRED_AUTOMATION_SINCE)->delete();

    if ($value !== null) {
        Setting::query()->create(['key' => Setting::TRIAL_EXPIRED_AUTOMATION_SINCE, 'value' => $value]);
    }
}

/**
 * Lead aberto com teste vencido em 27/09/2026 10:00 (São Paulo), produto com texto e WhatsApp válido.
 *
 * @param  array<string, mixed>  $attributes
 */
function candidatesLead(array $attributes = []): Lead
{
    return Lead::query()->create([
        'company_name' => 'Lead candidato '.uniqid(),
        'status' => 'contacted',
        'whatsapp' => '5551995179173',
        'prospect_product_id' => test()->product->id,
        'trial_started_at' => '2026-09-20 10:00',
        'trial_ends_at' => '2026-09-27 10:00',
        ...$attributes,
    ]);
}

/** Mensagem da conversa gravada no instante informado (UTC; created_at define a ordem). */
function candidatesMessage(Lead $lead, string $direction, string $at): LeadActivity
{
    $activity = $lead->activities()->create([
        'type' => 'whatsapp',
        'direction' => $direction,
        'contacted_at' => $at,
        'description' => $direction === LeadActivity::DIRECTION_INBOUND ? 'Mensagem do cliente.' : 'Mensagem da empresa.',
        'provider_message_id' => 'msg_'.fake()->unique()->uuid(),
        'message_status' => $direction === LeadActivity::DIRECTION_OUTBOUND ? LeadActivity::MESSAGE_STATUS_SERVER : null,
    ]);

    $activity->forceFill(['created_at' => $at, 'updated_at' => $at])->save();

    return $activity;
}

/** @return TestResponse<JsonResponse> */
function candidatesList(): TestResponse
{
    return test()->getJson(route('api.prospects.automation-candidates.trial-expired'), candidatesToken())->assertOk();
}

/** @return list<int> */
function candidatesIds(): array
{
    return array_column(candidatesList()->json('data'), 'lead_id');
}

function candidatesEligible(Lead $lead): bool
{
    return test()->getJson(route('api.prospects.automation-state', $lead), candidatesToken())
        ->assertOk()
        ->json('automations.trial_expired.eligible');
}

/** Lista e automation-state concordam sobre o lead. */
function candidatesExpect(Lead $lead, bool $candidate): void
{
    expect(in_array($lead->id, candidatesIds(), true))->toBe($candidate)
        ->and(candidatesEligible($lead))->toBe($candidate);
}

test('the candidates endpoint requires the prospect token', function () {
    candidatesLead();

    $this->getJson(route('api.prospects.automation-candidates.trial-expired'))->assertUnauthorized();
    $this->getJson(route('api.prospects.automation-candidates.trial-expired'), ['Authorization' => 'Bearer invalido'])->assertUnauthorized();
});

test('a candidate carries only what the execution needs', function () {
    $lead = candidatesLead(['whatsapp' => '(51) 99517-9173']);

    candidatesList()->assertExactJson([
        'data' => [[
            'lead_id' => $lead->id,
            'automation_key' => 'trial_expired:2026-09-27',
            'whatsapp' => '5551995179173',
            'message' => CANDIDATES_TEXT,
            // 27/09 10:00 em São Paulo = 13:00 UTC.
            'trial_ends_at' => '2026-09-27T13:00:00.000000Z',
        ]],
    ]);
});

test('the automation state exposes the expiration message and the real eligibility', function () {
    $lead = candidatesLead();

    $this->getJson(route('api.prospects.automation-state', $lead), candidatesToken())
        ->assertOk()
        ->assertJsonPath('automations.trial_expired', [
            'eligible' => true,
            'automation_key' => 'trial_expired:2026-09-27',
            'message' => CANDIDATES_TEXT,
        ]);

    $this->product->update(['trial_expiration_text' => null]);

    // Sem texto: inelegível, mas a chave do vencimento (CRM-WA-19) continua informada.
    $this->getJson(route('api.prospects.automation-state', $lead), candidatesToken())
        ->assertOk()
        ->assertJsonPath('automations.trial_expired', [
            'eligible' => false,
            'automation_key' => 'trial_expired:2026-09-27',
            'message' => null,
        ]);
});

test('eligibility follows each condition of the rule', function (Closure $setup, bool $candidate) {
    $lead = $setup(candidatesLead());

    candidatesExpect($lead, $candidate);
})->with([
    'elegível' => [fn (Lead $lead) => $lead, true],
    'sem produto' => [fn (Lead $lead) => tap($lead)->update(['prospect_product_id' => null]), false],
    'produto sem texto' => [fn (Lead $lead) => tap($lead, fn () => test()->product->update(['trial_expiration_text' => null])), false],
    'texto vazio' => [fn (Lead $lead) => tap($lead, fn () => test()->product->update(['trial_expiration_text' => ''])), false],
    'texto só com espaços' => [fn (Lead $lead) => tap($lead, fn () => test()->product->update(['trial_expiration_text' => "   \n\t "])), false],
    'produto inativo com texto' => [fn (Lead $lead) => tap($lead, fn () => test()->product->update(['active' => false])), true],
    'sem WhatsApp' => [fn (Lead $lead) => tap($lead)->update(['whatsapp' => null]), false],
    'WhatsApp inválido' => [fn (Lead $lead) => tap($lead)->update(['whatsapp' => '12345']), false],
    'convertido' => [fn (Lead $lead) => tap($lead)->update(['status' => 'converted']), false],
    'perdido' => [fn (Lead $lead) => tap($lead)->update(['status' => 'lost']), false],
    'novo' => [fn (Lead $lead) => tap($lead)->update(['status' => 'new']), true],
    'interessado' => [fn (Lead $lead) => tap($lead)->update(['status' => 'interested']), true],
    'reunião' => [fn (Lead $lead) => tap($lead)->update(['status' => 'meeting']), true],
    'teste ativo' => [fn (Lead $lead) => tap($lead)->update(['trial_ends_at' => '2026-10-05 10:00']), false],
    'teste não iniciado' => [fn (Lead $lead) => tap($lead)->update(['trial_started_at' => null, 'trial_ends_at' => null]), false],
    'chave reservada' => [fn (Lead $lead) => tap($lead, fn () => LeadAutomationExecution::reserve($lead, 'trial_expired:2026-09-27')), false],
    'outra chave reservada' => [fn (Lead $lead) => tap($lead, fn () => LeadAutomationExecution::reserve($lead, 'trial_expired:2026-09-20')), true],
]);

test('a soft-deleted lead is never a candidate', function () {
    $lead = candidatesLead();
    $lead->delete();

    expect(candidatesIds())->toBe([]);
});

test('outside business hours there are no candidates', function (int $day, int $hour, int $minute, int $second, bool $open) {
    $lead = candidatesLead();

    // Instantes em UTC (São Paulo = UTC-3).
    $this->travelTo(now()->setDate(2026, 9, $day)->setTime($hour, $minute, $second));

    expect(candidatesList()->json('data'))->toHaveCount($open ? 1 : 0)
        ->and(candidatesEligible($lead))->toBe($open);
})->with([
    'segunda 07:59:59' => [28, 10, 59, 59, false],
    'segunda 08:00' => [28, 11, 0, 0, true],
    'segunda 17:59:59' => [28, 20, 59, 59, true],
    'segunda 18:00' => [28, 21, 0, 0, false],
    'terça 00:00' => [29, 3, 0, 0, false],
    'sábado 12:00' => [26, 15, 0, 0, false],
    'domingo 12:00' => [27, 15, 0, 0, false],
]);

test('business hours come from the saved WhatsappBusinessHours configuration', function () {
    $lead = candidatesLead();
    app(WhatsappBusinessHours::class)->save([
        'timezone' => Lead::FOLLOW_UP_TIMEZONE,
        'days' => array_fill_keys(range(1, 7), ['active' => true, 'opens_at' => '13:00', 'closes_at' => '14:00']),
    ]);

    // Segunda 12:00: fora da janela salva (13:00–14:00).
    candidatesExpect($lead, false);

    // Sábado 13:30: dia ativo na configuração salva.
    $this->travelTo(now()->setDate(2026, 10, 3)->setTime(16, 30, 0));
    candidatesExpect($lead, true);
});

test('a lead awaiting a reply is blocked only until the company answers', function () {
    $lead = candidatesLead();
    candidatesMessage($lead, LeadActivity::DIRECTION_OUTBOUND, '2026-09-28 13:00:00');
    candidatesMessage($lead, LeadActivity::DIRECTION_INBOUND, '2026-09-28 14:00:00');

    candidatesExpect($lead, false);
    // Bloqueio temporário: nada é reservado enquanto a conversa está pendente.
    expect(LeadAutomationExecution::query()->count())->toBe(0);

    candidatesMessage($lead, LeadActivity::DIRECTION_OUTBOUND, '2026-09-28 14:30:00');

    candidatesExpect($lead, true);
});

test('reading the inbound message does not unblock the lead', function () {
    $lead = candidatesLead();
    candidatesMessage($lead, LeadActivity::DIRECTION_INBOUND, '2026-09-28 14:00:00')->forceFill(['read_at' => now()])->save();

    candidatesExpect($lead, false);
});

test('the lead becomes a candidate exactly at the expiration instant', function () {
    // Vence na segunda 28/09 às 12:00 em São Paulo (15:00 UTC), dentro do horário.
    $lead = candidatesLead(['trial_ends_at' => '2026-09-28 12:00']);

    $this->travelTo(now()->setDate(2026, 9, 28)->setTime(14, 59, 59));
    candidatesExpect($lead, false);

    $this->travelTo(now()->setDate(2026, 9, 28)->setTime(15, 0, 0));
    candidatesExpect($lead, true);
    candidatesList()->assertJsonPath('data.0.automation_key', 'trial_expired:2026-09-28');
});

test('trials that expired before the automation was switched on are not sent retroactively', function () {
    candidatesSince('2026-09-27T10:00:00-03:00');

    $before = candidatesLead(['trial_ends_at' => '2026-09-27 09:59:59']);
    $atCutoff = candidatesLead(['trial_ends_at' => '2026-09-27 10:00']);
    $after = candidatesLead(['trial_ends_at' => '2026-09-28 08:00']);

    expect(candidatesIds())->toBe([$atCutoff->id, $after->id])
        ->and(candidatesEligible($before))->toBeFalse()
        ->and(candidatesEligible($atCutoff))->toBeTrue();
});

test('the automation is off until the cutoff setting exists and is valid', function (?string $since) {
    $lead = candidatesLead();
    candidatesSince($since);

    candidatesExpect($lead, false);
})->with([
    'ausente' => [null],
    'vazio' => [''],
    'inválido' => ['ontem à tarde'],
]);

test('a reserved key disappears from the list and concurrent reservations still yield a single winner', function () {
    $lead = candidatesLead();
    $other = candidatesLead();
    $key = candidatesList()->json('data.0.automation_key');
    $reserve = fn () => $this->postJson(
        route('api.prospects.automation-executions.reserve', $lead),
        ['automation_key' => $key],
        candidatesToken(),
    );

    // Duas execuções do workflow com o mesmo candidato: só a primeira reserva envia.
    $reserve()->assertCreated()->assertExactJson(['reserved' => true]);
    $reserve()->assertOk()->assertExactJson(['reserved' => false]);

    expect(candidatesIds())->toBe([$other->id])
        ->and(candidatesEligible($lead))->toBeFalse()
        ->and(LeadAutomationExecution::query()->where('lead_id', $lead->id)->count())->toBe(1);
});

test('candidates are ordered by expiration, oldest first', function () {
    $newer = candidatesLead(['trial_ends_at' => '2026-09-27 10:00']);
    $older = candidatesLead(['trial_ends_at' => '2026-09-10 10:00']);
    $middle = candidatesLead(['trial_ends_at' => '2026-09-20 10:00']);

    expect(candidatesIds())->toBe([$older->id, $middle->id, $newer->id]);
});

test('the candidates endpoint is read-only', function () {
    Http::fake();

    $lead = candidatesLead([
        'next_follow_up_at' => '2026-09-30 09:00',
        'last_contacted_at' => '2026-09-27 11:00:00',
    ]);
    candidatesMessage($lead, LeadActivity::DIRECTION_OUTBOUND, '2026-09-27 14:00:00');
    $lead->activities()->create(['type' => 'note', 'description' => 'Nota anterior.']);

    $leadRow = fn () => (array) DB::table('leads')->where('id', $lead->id)->first();
    $activityRows = fn () => DB::table('lead_activities')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
    $leadBefore = $leadRow();
    $activitiesBefore = $activityRows();

    $this->travel(5)->minutes();

    DB::enableQueryLog();
    expect(candidatesIds())->toBe([$lead->id])->and(candidatesIds())->toBe([$lead->id]);
    $writes = collect(DB::getQueryLog())->pluck('query')
        ->filter(fn (string $sql) => preg_match('/^\s*(insert|update|delete|replace)\b/i', $sql) === 1)
        ->reject(fn (string $sql) => str_contains($sql, 'cache'))
        ->values()->all();
    DB::disableQueryLog();

    // Status, follow-up, último contato, datas do teste e histórico ficam iguais; nada é reservado.
    expect($leadRow())->toBe($leadBefore)
        ->and($activityRows())->toBe($activitiesBefore)
        ->and(LeadAutomationExecution::query()->count())->toBe(0)
        ->and($writes)->toBe([]);

    Http::assertNothingSent();
});

test('the candidates endpoint uses a constant number of queries', function () {
    $count = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        candidatesList();
        // settings fica de fora: o horário de atendimento é lido uma vez por request (scoped).
        $queries = collect(DB::getQueryLog())->pluck('query')->reject(fn (string $sql) => str_contains($sql, 'settings'))->count();
        DB::disableQueryLog();

        return $queries;
    };

    candidatesLead();
    $single = $count();

    foreach (range(1, 5) as $i) {
        candidatesLead();
    }

    // Leads, produtos (eager load) e reservas: uma consulta cada, qualquer que seja o volume.
    expect($single)->toBe(3)
        ->and(candidatesIds())->toHaveCount(6)
        ->and($count())->toBe($single);
});
