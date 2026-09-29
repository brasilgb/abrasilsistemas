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
 * CRM-WA-19: elegibilidade da automação trial_expired no automation-state (CRM-WA-17).
 * Elegível = trial_state expired: prospect aberto, teste iniciado e término antes de hoje em
 * São Paulo. O término é o último dia do teste; o vencimento acontece à 00:00 do dia seguinte.
 * CRM-WA-24 acrescentou condições ao eligible (texto do produto, horário, corte, conversa,
 * reserva): aqui elas ficam sempre satisfeitas (produto com texto, atendimento 24h, corte antigo)
 * para os testes continuarem medindo só a regra do teste; as condições novas têm o próprio arquivo.
 */

const TRIAL_EXPIRED_TEXT = 'Seu período de teste terminou.';

beforeEach(function () {
    // 28/09/2026 12:00 em São Paulo (15:00 UTC).
    $this->travelTo(now()->setDate(2026, 9, 28)->setTime(15, 0, 0));

    Setting::query()->create(['key' => Setting::TRIAL_EXPIRED_AUTOMATION_SINCE, 'value' => '2026-01-01T00:00:00Z']);
    app(WhatsappBusinessHours::class)->save([
        'timezone' => Lead::FOLLOW_UP_TIMEZONE,
        'days' => array_fill_keys(range(1, 7), ['active' => true, 'opens_at' => '00:00', 'closes_at' => '24:00']),
    ]);
    $this->product = ProspectProduct::query()->create(['name' => 'VetorOS', 'trial_days' => 7, 'trial_expiration_text' => TRIAL_EXPIRED_TEXT]);
});

/** @return array<string, string> */
function trialExpiredToken(): array
{
    return ['Authorization' => 'Bearer '.config('services.ab_prospect.token')];
}

/** @param  array<string, mixed>  $attributes */
function trialExpiredLead(array $attributes = []): Lead
{
    return Lead::query()->create([
        'company_name' => 'Lead teste vencido '.uniqid(),
        'product' => 'vetoros',
        'status' => 'meeting',
        'whatsapp' => '5551995179173',
        'prospect_product_id' => test()->product->id,
        ...$attributes,
    ]);
}

/** @return TestResponse<JsonResponse> */
function trialExpiredState(Lead|int $lead): TestResponse
{
    return test()->getJson(route('api.prospects.automation-state', $lead), trialExpiredToken());
}

test('trial_expired eligibility follows the trial and the commercial status', function (array $attributes, bool $eligible, ?string $key) {
    $lead = trialExpiredLead($attributes);

    trialExpiredState($lead)
        ->assertOk()
        ->assertJsonPath('automations.trial_expired', [
            'eligible' => $eligible,
            'automation_key' => $key,
            'message' => TRIAL_EXPIRED_TEXT,
            'last_execution' => null,
        ]);

    expect($lead->refresh()->trialExpiredAutomationKey())->toBe($key);
})->with([
    'sem teste' => [[], false, null],
    'só término, sem início' => [['trial_ends_at' => '2026-09-20'], false, null],
    'ativo sem término' => [['trial_started_at' => '2026-09-01'], false, null],
    'ativo, termina depois' => [['trial_started_at' => '2026-09-20', 'trial_ends_at' => '2026-10-05'], false, null],
    'ativo, termina hoje' => [['trial_started_at' => '2026-09-20', 'trial_ends_at' => '2026-09-28'], false, null],
    'vencido ontem' => [['trial_started_at' => '2026-09-10', 'trial_ends_at' => '2026-09-27'], true, 'trial_expired:2026-09-27'],
    'vencido, novo' => [['status' => 'new', 'trial_started_at' => '2026-09-01', 'trial_ends_at' => '2026-09-15'], true, 'trial_expired:2026-09-15'],
    'vencido, contatado' => [['status' => 'contacted', 'trial_started_at' => '2026-09-01', 'trial_ends_at' => '2026-09-15'], true, 'trial_expired:2026-09-15'],
    'vencido, interessado' => [['status' => 'interested', 'trial_started_at' => '2026-09-01', 'trial_ends_at' => '2026-09-15'], true, 'trial_expired:2026-09-15'],
    'vencido, convertido' => [['status' => 'converted', 'trial_started_at' => '2026-09-01', 'trial_ends_at' => '2026-09-15'], false, null],
    'vencido, perdido' => [['status' => 'lost', 'trial_started_at' => '2026-09-01', 'trial_ends_at' => '2026-09-15'], false, null],
]);

test('trial_expired eligibility agrees with trial_state and is_closed', function (string $status) {
    $lead = trialExpiredLead(['status' => $status, 'trial_started_at' => '2026-09-01', 'trial_ends_at' => '2026-09-15']);

    $response = trialExpiredState($lead)->assertOk();

    expect($response->json('automations.trial_expired.eligible'))
        ->toBe($response->json('trial.state') === 'expired')
        ->toBe(! $response->json('is_closed'));
})->with(array_keys(Lead::STATUSES));

test('trial_expired becomes eligible exactly when the last trial day ends in São Paulo', function () {
    $lead = trialExpiredLead(['trial_started_at' => '2026-09-28', 'trial_ends_at' => '2026-10-05']);

    // 05/10 23:59:59 em São Paulo (06/10 02:59:59 UTC): último instante do teste.
    $this->travelTo(now()->setDate(2026, 10, 6)->setTime(2, 59, 59));
    trialExpiredState($lead)
        ->assertJsonPath('trial.state', 'active')
        ->assertJsonPath('automations.trial_expired', ['eligible' => false, 'automation_key' => null, 'message' => TRIAL_EXPIRED_TEXT, 'last_execution' => null]);

    // 06/10 00:00:00 em São Paulo (03:00 UTC): agora == fim do teste, já vencido.
    $this->travelTo(now()->setDate(2026, 10, 6)->setTime(3, 0, 0));
    trialExpiredState($lead)
        ->assertJsonPath('trial.state', 'expired')
        ->assertJsonPath('automations.trial_expired', ['eligible' => true, 'automation_key' => 'trial_expired:2026-10-05', 'message' => TRIAL_EXPIRED_TEXT, 'last_execution' => null]);
});

test('the trial_expired key derives from trial_ends_at, not from the day of the query', function () {
    $lead = trialExpiredLead(['trial_started_at' => '2026-09-28', 'trial_ends_at' => '2026-10-05']);

    foreach ([[2026, 10, 6, 3], [2026, 10, 6, 23], [2026, 10, 20, 12], [2027, 1, 15, 2]] as [$year, $month, $day, $hour]) {
        $this->travelTo(now()->setDate($year, $month, $day)->setTime($hour, 30, 0));

        trialExpiredState($lead)->assertJsonPath('automations.trial_expired.automation_key', 'trial_expired:2026-10-05');
    }
});

test('a new trial end date produces a new key', function () {
    $lead = trialExpiredLead(['trial_started_at' => '2026-09-01', 'trial_ends_at' => '2026-09-15']);
    trialExpiredState($lead)->assertJsonPath('automations.trial_expired.automation_key', 'trial_expired:2026-09-15');

    $lead->update(['trial_ends_at' => '2026-09-25']);
    trialExpiredState($lead)->assertJsonPath('automations.trial_expired.automation_key', 'trial_expired:2026-09-25');

    // Prorrogado para o futuro: volta a ativo, sem elegibilidade.
    $lead->update(['trial_ends_at' => '2026-10-10']);
    trialExpiredState($lead)->assertJsonPath('automations.trial_expired', ['eligible' => false, 'automation_key' => null, 'message' => TRIAL_EXPIRED_TEXT, 'last_execution' => null]);
});

test('a soft-deleted lead with an expired trial is not found', function () {
    $lead = trialExpiredLead(['trial_started_at' => '2026-09-01', 'trial_ends_at' => '2026-09-15']);
    $lead->delete();

    trialExpiredState($lead->id)->assertNotFound();
});

test('the trial_expired key is accepted by the CRM-WA-18 reservation and the reserved lead is no longer eligible', function () {
    $lead = trialExpiredLead(['trial_started_at' => '2026-09-01', 'trial_ends_at' => '2026-09-15']);
    $key = trialExpiredState($lead)->json('automations.trial_expired.automation_key');
    $reserve = fn () => $this->postJson(
        route('api.prospects.automation-executions.reserve', $lead),
        ['automation_key' => $key],
        trialExpiredToken(),
    );

    $reserve()->assertCreated()->assertExactJson(['reserved' => true]);
    $reserve()->assertOk()->assertExactJson(['reserved' => false]);

    // CRM-WA-24: chave reservada deixa de ser elegível; a chave do vencimento continua informada.
    // CRM-WA-28: reservada sem mensagem vinculada = envio não confirmado, sem resposta.
    trialExpiredState($lead)->assertJsonPath('automations.trial_expired', [
        'eligible' => false,
        'automation_key' => $key,
        'message' => TRIAL_EXPIRED_TEXT,
        'last_execution' => [
            'automation_key' => $key,
            'executed_at' => '2026-09-28T15:00:00.000000Z',
            'sent_at' => null,
            'replied' => false,
            'replied_at' => null,
        ],
    ]);
    expect(LeadAutomationExecution::query()->where('lead_id', $lead->id)->pluck('automation_key')->all())->toBe([$key]);
});

test('querying trial_expired eligibility has no side effects', function () {
    Http::fake();

    $lead = trialExpiredLead([
        'trial_started_at' => '2026-09-01',
        'trial_ends_at' => '2026-09-15',
        'next_follow_up_at' => '2026-09-30 09:00',
        'last_contacted_at' => '2026-09-27 11:00:00',
    ]);
    $lead->activities()->create(['type' => 'note', 'description' => 'Nota anterior.']);

    $leadRow = fn () => (array) DB::table('leads')->where('id', $lead->id)->first();
    $leadBefore = $leadRow();
    $activitiesBefore = LeadActivity::query()->count();

    $this->travel(5)->minutes();

    DB::enableQueryLog();
    trialExpiredState($lead)->assertOk()->assertJsonPath('automations.trial_expired.eligible', true);
    trialExpiredState($lead)->assertOk()->assertJsonPath('automations.trial_expired.eligible', true);
    $writes = collect(DB::getQueryLog())->pluck('query')
        ->filter(fn (string $sql) => preg_match('/^\s*(insert|update|delete|replace)\b/i', $sql) === 1)
        ->reject(fn (string $sql) => str_contains($sql, 'cache'))
        ->values()->all();
    DB::disableQueryLog();

    // updated_at, status, last_contacted_at, next_follow_up_at e as datas do teste ficam iguais.
    expect($leadRow())->toBe($leadBefore)
        ->and(LeadActivity::query()->count())->toBe($activitiesBefore)
        ->and(LeadAutomationExecution::query()->count())->toBe(0)
        ->and($writes)->toBe([]);

    // Nenhuma chamada HTTP de saída (n8n, WAHA, WhatsApp).
    Http::assertNothingSent();
});
