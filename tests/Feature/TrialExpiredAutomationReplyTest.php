<?php

use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\LeadAutomationExecution;
use App\Models\ProspectProduct;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

/*
 * CRM-WA-28: resposta do cliente depois da automação trial_expired. O /whatsapp/log com
 * automation_key vincula a mensagem enviada à execução reservada; o automation-state expõe a
 * última execução e se a mensagem seguinte da conversa foi uma inbound. Nenhum efeito comercial.
 */

const TRIAL_REPLY_KEY = 'trial_expired:2026-09-27';

beforeEach(function () {
    // 29/09/2026 12:00 em São Paulo (15:00 UTC).
    $this->travelTo(now()->setDate(2026, 9, 29)->setTime(15, 0, 0));

    $this->product = ProspectProduct::query()->create(['name' => 'VetorOS', 'trial_days' => 7, 'trial_expiration_text' => 'Seu teste terminou.']);
});

/** @return array<string, string> */
function trialReplyToken(): array
{
    return ['Authorization' => 'Bearer '.config('services.ab_prospect.token')];
}

/** @param  array<string, mixed>  $attributes */
function trialReplyLead(array $attributes = []): Lead
{
    return Lead::query()->create([
        'company_name' => 'Lead resposta '.uniqid(),
        'status' => 'contacted',
        'whatsapp' => '5551995179173',
        'prospect_product_id' => test()->product->id,
        'trial_started_at' => '2026-09-21',
        'trial_ends_at' => '2026-09-27',
        'next_follow_up_at' => '2026-10-02 10:00',
        ...$attributes,
    ]);
}

function trialReplyReserve(Lead $lead, string $key = TRIAL_REPLY_KEY): void
{
    test()->postJson(route('api.prospects.automation-executions.reserve', $lead), ['automation_key' => $key], trialReplyToken())
        ->assertCreated();
}

/**
 * Envio registrado pelo /whatsapp/log, como no workflow do n8n (com automation_key) ou no envio
 * manual pelo CRM (sem automation_key).
 *
 * @return TestResponse<JsonResponse>
 */
function trialReplyLog(Lead $lead, string $messageId, ?string $automationKey = TRIAL_REPLY_KEY): TestResponse
{
    return test()->postJson("/api/prospects/{$lead->id}/whatsapp/log", array_filter([
        'message_id' => $messageId,
        'status' => 'PENDING',
        'message' => 'Seu teste terminou.',
        'automation_key' => $automationKey,
    ]), trialReplyToken());
}

/** @return TestResponse<JsonResponse> */
function trialReplyInbound(string $messageId, string $text = 'Quero continuar'): TestResponse
{
    return test()->postJson('/api/whatsapp/inbound', [
        'session' => 'vetoros1-1',
        'provider_message_id' => $messageId,
        'from' => '555195179173',
        'message' => $text,
    ], trialReplyToken());
}

/** @return TestResponse<JsonResponse> */
function trialReplyState(Lead $lead): TestResponse
{
    return test()->getJson(route('api.prospects.automation-state', $lead), trialReplyToken())->assertOk();
}

function trialReplyExecution(Lead $lead, string $key = TRIAL_REPLY_KEY): LeadAutomationExecution
{
    return LeadAutomationExecution::query()->where('lead_id', $lead->id)->where('automation_key', $key)->sole();
}

test('the log API links the automation message to the reserved execution', function () {
    $lead = trialReplyLead();
    trialReplyReserve($lead);

    $response = trialReplyLog($lead, 'AUTO1')->assertCreated();

    expect(trialReplyExecution($lead)->lead_activity_id)->toBe($response->json('activity_id'));
});

test('the log API keeps the first linked message of an execution', function () {
    $lead = trialReplyLead();
    trialReplyReserve($lead);
    $first = trialReplyLog($lead, 'AUTO1')->json('activity_id');

    trialReplyLog($lead, 'AUTO1')->assertOk()->assertJsonPath('idempotent', true);
    $second = LeadActivity::query()->findOrFail(trialReplyLog($lead, 'AUTO2')->assertCreated()->json('activity_id'));

    expect(LeadAutomationExecution::linkActivity($lead, TRIAL_REPLY_KEY, $second))->toBeFalse()
        ->and(LeadAutomationExecution::linkActivity($lead, 'trial_expired:2000-01-01', $second))->toBeFalse()
        ->and(trialReplyExecution($lead)->lead_activity_id)->toBe($first);
});

test('the log API links a message the fromMe echo already recorded', function () {
    $lead = trialReplyLead();
    trialReplyReserve($lead);
    $echo = $lead->activities()->create([
        'type' => 'whatsapp',
        'direction' => LeadActivity::DIRECTION_OUTBOUND,
        'contacted_at' => now(),
        'description' => 'Seu teste terminou.',
        'provider_message_id' => 'AUTO1',
        'message_status' => 'SERVER',
    ]);

    trialReplyLog($lead, 'AUTO1')->assertOk()->assertJsonPath('activity_id', $echo->id);

    expect(trialReplyExecution($lead)->lead_activity_id)->toBe($echo->id);
});

test('the log API records the message without creating an execution for an unreserved key', function () {
    $lead = trialReplyLead();

    trialReplyLog($lead, 'AUTO1')->assertCreated();

    expect(LeadAutomationExecution::query()->count())->toBe(0)
        ->and($lead->activities()->where('provider_message_id', 'AUTO1')->exists())->toBeTrue();
});

test('the log API does not link a message to the execution of another lead', function () {
    $lead = trialReplyLead();
    $other = trialReplyLead(['whatsapp' => '5551988887777']);
    trialReplyReserve($lead);

    trialReplyLog($other, 'AUTO1')->assertCreated();

    expect(trialReplyExecution($lead)->lead_activity_id)->toBeNull();
});

test('a message cannot be linked to two executions', function () {
    $lead = trialReplyLead();
    trialReplyReserve($lead);
    trialReplyReserve($lead, 'trial_expired:2026-09-20');
    $activityId = trialReplyLog($lead, 'AUTO1')->json('activity_id');

    expect(LeadAutomationExecution::linkActivity($lead, 'trial_expired:2026-09-20', LeadActivity::query()->findOrFail($activityId)))->toBeFalse()
        ->and(trialReplyExecution($lead, 'trial_expired:2026-09-20')->lead_activity_id)->toBeNull();
});

test('the log API rejects an invalid automation key or one on an inbound message', function (array $payload) {
    $lead = trialReplyLead();

    $this->postJson("/api/prospects/{$lead->id}/whatsapp/log", [
        'message_id' => 'AUTO1',
        'status' => 'PENDING',
        'message' => 'Teste',
        ...$payload,
    ], trialReplyToken())->assertUnprocessable()->assertJsonValidationErrors('automation_key');
})->with([
    'formato inválido' => [['automation_key' => 'trial expired']],
    'longa demais' => [['automation_key' => str_repeat('a', 151)]],
    'inbound' => [['automation_key' => TRIAL_REPLY_KEY, 'direction' => 'inbound', 'status' => null]],
]);

test('A: an inbound after the automation message is recognized as a reply', function () {
    $lead = trialReplyLead();
    trialReplyReserve($lead);
    trialReplyLog($lead, 'AUTO1')->assertCreated();

    $this->travel(5)->minutes();
    trialReplyInbound('IN1')->assertCreated();

    trialReplyState($lead)->assertJsonPath('automations.trial_expired.last_execution', [
        'automation_key' => TRIAL_REPLY_KEY,
        'executed_at' => '2026-09-29T15:00:00.000000Z',
        'sent_at' => '2026-09-29T15:00:00.000000Z',
        'replied' => true,
        'replied_at' => '2026-09-29T15:05:00.000000Z',
    ]);
});

test('B: an inbound before the automation message is not a reply', function () {
    $lead = trialReplyLead();
    trialReplyInbound('IN1')->assertCreated();

    $this->travel(5)->minutes();
    trialReplyReserve($lead);
    trialReplyLog($lead, 'AUTO1')->assertCreated();

    trialReplyState($lead)
        ->assertJsonPath('automations.trial_expired.last_execution.sent_at', '2026-09-29T15:05:00.000000Z')
        ->assertJsonPath('automations.trial_expired.last_execution.replied', false)
        ->assertJsonPath('automations.trial_expired.last_execution.replied_at', null);
});

test('C: a lead without a trial_expired execution has no reply state', function () {
    $lead = trialReplyLead();
    trialReplyLog($lead, 'MANUAL1', null)->assertCreated();
    $this->travel(5)->minutes();
    trialReplyInbound('IN1')->assertCreated();

    trialReplyState($lead)->assertJsonPath('automations.trial_expired.last_execution', null);
});

test('D: an execution of another automation is not a trial_expired reply', function (string $key) {
    $lead = trialReplyLead();
    trialReplyReserve($lead, $key);
    trialReplyLog($lead, 'AUTO1', $key)->assertCreated();
    $this->travel(5)->minutes();
    trialReplyInbound('IN1')->assertCreated();

    expect(trialReplyExecution($lead, $key)->lead_activity_id)->not->toBeNull();
    trialReplyState($lead)->assertJsonPath('automations.trial_expired.last_execution', null);
})->with([
    'outra automação' => ['follow_up:2026-09-29'],
    'prefixo parecido' => ['trialXexpired:2026-09-27'],
    'sem os dois-pontos' => ['trial_expired_v2:2026-09-27'],
]);

test('E: a duplicated inbound has no duplicated effect', function () {
    $lead = trialReplyLead();
    trialReplyReserve($lead);
    trialReplyLog($lead, 'AUTO1')->assertCreated();
    $this->travel(5)->minutes();
    trialReplyInbound('IN1')->assertCreated();
    $before = $lead->fresh()->only(['status', 'next_follow_up_at', 'last_contacted_at', 'trial_ends_at']);

    $this->travel(3)->minutes();
    trialReplyInbound('IN1')->assertOk()->assertJsonPath('idempotent', true);

    expect($lead->activities()->where('provider_message_id', 'IN1')->count())->toBe(1)
        ->and($lead->fresh()->only(array_keys($before)))->toEqual($before);
    trialReplyState($lead)->assertJsonPath('automations.trial_expired.last_execution.replied_at', '2026-09-29T15:05:00.000000Z');
});

test('F: a later outbound takes the reply away from the automation', function (?string $manualKey) {
    $lead = trialReplyLead();
    trialReplyReserve($lead);
    trialReplyLog($lead, 'AUTO1')->assertCreated();

    $this->travel(2)->minutes();
    // Envio manual pelo CRM (sem chave) ou mensagem do celular (eco fromMe): outbound sem vínculo.
    trialReplyLog($lead, 'MANUAL1', $manualKey)->assertCreated();

    $this->travel(5)->minutes();
    trialReplyInbound('IN1')->assertCreated();

    trialReplyState($lead)
        ->assertJsonPath('automations.trial_expired.last_execution.sent_at', '2026-09-29T15:00:00.000000Z')
        ->assertJsonPath('automations.trial_expired.last_execution.replied', false);
})->with([
    'envio manual' => [null],
    'chave repetida' => [TRIAL_REPLY_KEY],
]);

test('F: a reply stays with the automation when the manual message comes after it', function () {
    $lead = trialReplyLead();
    trialReplyReserve($lead);
    trialReplyLog($lead, 'AUTO1')->assertCreated();
    $this->travel(5)->minutes();
    trialReplyInbound('IN1')->assertCreated();
    $this->travel(2)->minutes();
    trialReplyLog($lead, 'MANUAL1', null)->assertCreated();
    $this->travel(2)->minutes();
    trialReplyInbound('IN2')->assertCreated();

    trialReplyState($lead)
        ->assertJsonPath('automations.trial_expired.last_execution.replied', true)
        ->assertJsonPath('automations.trial_expired.last_execution.replied_at', '2026-09-29T15:05:00.000000Z');
});

test('an inbound in the same second as the automation message follows the recording order', function () {
    $lead = trialReplyLead();
    trialReplyReserve($lead);
    trialReplyLog($lead, 'AUTO1')->assertCreated();
    trialReplyInbound('IN1')->assertCreated();

    trialReplyState($lead)->assertJsonPath('automations.trial_expired.last_execution.replied', true);
});

test('a reserved execution without a confirmed message is never replied', function () {
    $lead = trialReplyLead();
    trialReplyReserve($lead);
    // WAHA falhou após a reserva: horas depois, um envio manual e a resposta do cliente.
    $this->travel(3)->hours();
    trialReplyLog($lead, 'MANUAL1', null)->assertCreated();
    $this->travel(5)->minutes();
    trialReplyInbound('IN1')->assertCreated();

    trialReplyState($lead)->assertJsonPath('automations.trial_expired.last_execution', [
        'automation_key' => TRIAL_REPLY_KEY,
        'executed_at' => '2026-09-29T15:00:00.000000Z',
        'sent_at' => null,
        'replied' => false,
        'replied_at' => null,
    ]);
});

test('an old trial_expired reply does not count for a newer execution', function () {
    $lead = trialReplyLead();
    trialReplyReserve($lead, 'trial_expired:2026-09-20');
    trialReplyLog($lead, 'AUTO_OLD', 'trial_expired:2026-09-20')->assertCreated();
    $this->travel(5)->minutes();
    trialReplyInbound('IN_OLD')->assertCreated();

    $this->travel(1)->days();
    trialReplyLog($lead, 'MANUAL1', null)->assertCreated();
    $this->travel(1)->hours();
    trialReplyReserve($lead);
    trialReplyLog($lead, 'AUTO1')->assertCreated();

    trialReplyState($lead)
        ->assertJsonPath('automations.trial_expired.last_execution.automation_key', TRIAL_REPLY_KEY)
        ->assertJsonPath('automations.trial_expired.last_execution.replied', false);
});

test('G: a reply does not change the commercial state of the lead', function (string $status) {
    $lead = trialReplyLead();
    trialReplyReserve($lead);
    trialReplyLog($lead, 'AUTO1')->assertCreated();
    $lead->update(['status' => $status]);
    $before = $lead->fresh()->only(['status', 'lost_reason', 'user_id', 'next_follow_up_at', 'trial_started_at', 'trial_ends_at']);
    $executions = LeadAutomationExecution::query()->count();

    $this->travel(5)->minutes();
    trialReplyInbound('IN1')->assertCreated();

    expect($lead->fresh()->only(array_keys($before)))->toEqual($before)
        ->and(LeadAutomationExecution::query()->count())->toBe($executions)
        ->and($lead->activities()->where('direction', LeadActivity::DIRECTION_OUTBOUND)->count())->toBe(1);
    trialReplyState($lead)
        ->assertJsonPath('status', $status)
        ->assertJsonPath('automations.trial_expired.last_execution.replied', true);
})->with(['contacted', 'converted', 'lost']);

test('reading the reply state does not write anything', function () {
    $lead = trialReplyLead();
    trialReplyReserve($lead);
    trialReplyLog($lead, 'AUTO1')->assertCreated();
    $this->travel(5)->minutes();
    trialReplyInbound('IN1')->assertCreated();
    $lead->refresh();

    $queries = [];
    DB::listen(function ($query) use (&$queries) {
        $queries[] = $query->sql;
    });
    trialReplyState($lead);

    expect(collect($queries)->filter(fn (string $sql) => preg_match('/^\s*(insert|update|delete)/i', $sql) === 1))->toBeEmpty();
});
