<?php

use App\Models\Lead;
use App\Models\LeadActivity;
use Illuminate\Support\Facades\DB;

/*
 * CRM-WA-17: estado comercial do lead para o n8n, somente leitura, protegido por prospect.token.
 * As regras vêm do model: isClosed, trial_state, follow_up_state e awaitingWhatsappReply.
 */

beforeEach(function () {
    // 28/09/2026 12:00 em São Paulo (15:00 UTC).
    $this->travelTo(now()->setDate(2026, 9, 28)->setTime(15, 0, 0));
});

function automationStateToken(): array
{
    return ['Authorization' => 'Bearer '.config('services.ab_prospect.token')];
}

/** @param  array<string, mixed>  $attributes */
function automationStateLead(array $attributes = []): Lead
{
    return Lead::query()->create([
        'company_name' => 'Lead automação '.uniqid(),
        'product' => 'vetoros',
        'status' => 'meeting',
        'whatsapp' => '5551995179173',
        ...$attributes,
    ]);
}

/** Mensagem da conversa gravada no instante informado (UTC; created_at define a ordem). */
function automationStateMessage(Lead $lead, string $direction, string $at): LeadActivity
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

function automationStateUrl(Lead|int $lead): string
{
    return route('api.prospects.automation-state', $lead);
}

test('a valid token reads the automation state of a lead', function () {
    $lead = automationStateLead([
        'trial_started_at' => '2026-09-28',
        'trial_ends_at' => '2026-10-05',
        'next_follow_up_at' => '2026-09-30 14:00',
    ]);
    automationStateMessage($lead, LeadActivity::DIRECTION_OUTBOUND, '2026-09-28 13:00:00');

    $this->getJson(automationStateUrl($lead), automationStateToken())
        ->assertOk()
        ->assertExactJson([
            'lead_id' => $lead->id,
            'status' => 'meeting',
            'is_closed' => false,
            'product' => 'vetoros',
            'trial' => [
                'state' => 'active',
                'started_at' => '2026-09-28',
                'ends_at' => '2026-10-05',
            ],
            'follow_up' => [
                'state' => 'upcoming',
                // 14:00 em Brasília = 17:00 UTC.
                'at' => '2026-09-30T17:00:00.000000Z',
            ],
            'whatsapp' => [
                'awaiting_reply' => false,
                'last_outbound_at' => '2026-09-28T13:00:00.000000Z',
                'last_inbound_at' => null,
            ],
            'automations' => [
                'trial_expired' => [
                    'eligible' => false,
                    'automation_key' => null,
                ],
            ],
        ]);
});

test('the automation state rejects a missing or invalid token before resolving the lead', function () {
    $lead = automationStateLead();

    $this->getJson(automationStateUrl($lead))->assertUnauthorized();
    $this->getJson(automationStateUrl($lead), ['Authorization' => 'Bearer token-errado'])->assertUnauthorized();
    $this->getJson(automationStateUrl(999999))->assertUnauthorized();
});

test('the automation state returns not found for an unknown lead', function () {
    $this->getJson(automationStateUrl(999999), automationStateToken())->assertNotFound();
});

test('the automation state returns not found for a soft-deleted lead', function () {
    $lead = automationStateLead();
    $lead->delete();

    expect(Lead::withTrashed()->find($lead->id))->not->toBeNull();

    $this->getJson(automationStateUrl($lead->id), automationStateToken())->assertNotFound();
});

test('the automation state reuses the closed-lead rule', function (string $status, bool $closed) {
    $lead = automationStateLead(['status' => $status]);

    $this->getJson(automationStateUrl($lead), automationStateToken())
        ->assertOk()
        ->assertJsonPath('status', $status)
        ->assertJsonPath('is_closed', $closed);
})->with([
    'novo' => ['new', false],
    'contatado' => ['contacted', false],
    'interessado' => ['interested', false],
    'reunião' => ['meeting', false],
    'convertido' => ['converted', true],
    'perdido' => ['lost', true],
]);

test('the automation state reports the trial state of CRM-WA-16', function (array $attributes, string $state, ?string $startedAt, ?string $endsAt) {
    $lead = automationStateLead($attributes);

    $this->getJson(automationStateUrl($lead), automationStateToken())
        ->assertOk()
        ->assertJsonPath('trial', [
            'state' => $state,
            'started_at' => $startedAt,
            'ends_at' => $endsAt,
        ]);

    expect($state)->toBe($lead->refresh()->trial_state);
})->with([
    'sem teste' => [[], 'none', null, null],
    'ativo sem término' => [['trial_started_at' => '2026-09-20'], 'active', '2026-09-20', null],
    'ativo, termina hoje' => [['trial_started_at' => '2026-09-20', 'trial_ends_at' => '2026-09-28'], 'active', '2026-09-20', '2026-09-28'],
    'vencido' => [['trial_started_at' => '2026-09-10', 'trial_ends_at' => '2026-09-27'], 'expired', '2026-09-10', '2026-09-27'],
    'convertido com datas' => [['status' => 'converted', 'trial_started_at' => '2026-09-20', 'trial_ends_at' => '2026-10-05'], 'none', '2026-09-20', '2026-10-05'],
]);

test('the automation state reports the real follow-up state', function (array $attributes, ?string $state, ?string $at) {
    $lead = automationStateLead($attributes);

    $this->getJson(automationStateUrl($lead), automationStateToken())
        ->assertOk()
        ->assertJsonPath('follow_up', ['state' => $state, 'at' => $at]);

    expect($state)->toBe($lead->refresh()->follow_up_state);
})->with([
    'sem follow-up' => [[], 'none', null],
    'atrasado' => [['next_follow_up_at' => '2026-09-28 09:00'], 'overdue', '2026-09-28T12:00:00.000000Z'],
    'hoje' => [['next_follow_up_at' => '2026-09-28 18:00'], 'today', '2026-09-28T21:00:00.000000Z'],
    'próximo' => [['next_follow_up_at' => '2026-09-29 09:00'], 'upcoming', '2026-09-29T12:00:00.000000Z'],
    // Encerrado: follow_up_state é nulo por regra do model, mesmo com data gravada.
    'perdido com data' => [['status' => 'lost', 'next_follow_up_at' => '2026-09-29 09:00'], null, '2026-09-29T12:00:00.000000Z'],
]);

test('the automation state reports the latest outbound and inbound messages of the conversation', function () {
    $lead = automationStateLead();
    automationStateMessage($lead, LeadActivity::DIRECTION_OUTBOUND, '2026-09-27 10:00:00');
    automationStateMessage($lead, LeadActivity::DIRECTION_INBOUND, '2026-09-27 11:00:00');
    automationStateMessage($lead, LeadActivity::DIRECTION_OUTBOUND, '2026-09-28 13:00:00');
    automationStateMessage($lead, LeadActivity::DIRECTION_INBOUND, '2026-09-28 12:00:00');

    // Fora da conversa: WhatsApp manual sem direction, nota e mensagens de outro lead.
    $lead->activities()->create(['type' => 'whatsapp', 'description' => 'Liguei pelo WhatsApp.'])
        ->forceFill(['created_at' => '2026-09-28 14:30:00'])->save();
    $lead->activities()->create(['type' => 'note', 'description' => 'Nota.']);
    $other = automationStateLead();
    automationStateMessage($other, LeadActivity::DIRECTION_OUTBOUND, '2026-09-28 14:50:00');
    automationStateMessage($other, LeadActivity::DIRECTION_INBOUND, '2026-09-28 14:55:00');

    // last_contacted_at é atualizado também pela inbound: não serve para a última abordagem.
    $lead->forceFill(['last_contacted_at' => '2026-09-28 14:40:00'])->save();

    $this->getJson(automationStateUrl($lead), automationStateToken())
        ->assertOk()
        ->assertJsonPath('whatsapp.last_outbound_at', '2026-09-28T13:00:00.000000Z')
        ->assertJsonPath('whatsapp.last_inbound_at', '2026-09-28T12:00:00.000000Z');
});

test('a lead without conversation has no WhatsApp timestamps', function () {
    $lead = automationStateLead();

    $this->getJson(automationStateUrl($lead), automationStateToken())
        ->assertOk()
        ->assertJsonPath('whatsapp', [
            'awaiting_reply' => false,
            'last_outbound_at' => null,
            'last_inbound_at' => null,
        ]);
});

test('awaiting_reply follows the awaiting WhatsApp reply rule', function (array $messages, bool $awaiting) {
    $lead = automationStateLead();

    foreach ($messages as [$direction, $at]) {
        automationStateMessage($lead, $direction, $at);
    }

    $this->getJson(automationStateUrl($lead), automationStateToken())
        ->assertOk()
        ->assertJsonPath('whatsapp.awaiting_reply', $awaiting);

    expect(Lead::query()->awaitingWhatsappReply()->whereKey($lead->id)->exists())->toBe($awaiting);
})->with([
    'só outbound' => [[['outbound', '2026-09-28 10:00:00']], false],
    'inbound sem resposta' => [[['outbound', '2026-09-28 10:00:00'], ['inbound', '2026-09-28 11:00:00']], true],
    'inbound respondida' => [[['inbound', '2026-09-28 10:00:00'], ['outbound', '2026-09-28 11:00:00']], false],
    'nova inbound após resposta' => [[['inbound', '2026-09-28 09:00:00'], ['outbound', '2026-09-28 10:00:00'], ['inbound', '2026-09-28 11:00:00']], true],
]);

test('awaiting_reply does not leak between leads and includes closed leads', function () {
    $awaiting = automationStateLead(['status' => 'converted']);
    automationStateMessage($awaiting, LeadActivity::DIRECTION_INBOUND, '2026-09-28 11:00:00');
    $answered = automationStateLead();
    automationStateMessage($answered, LeadActivity::DIRECTION_INBOUND, '2026-09-28 10:00:00');
    automationStateMessage($answered, LeadActivity::DIRECTION_OUTBOUND, '2026-09-28 10:30:00');

    $this->getJson(automationStateUrl($awaiting), automationStateToken())
        ->assertJsonPath('is_closed', true)
        ->assertJsonPath('whatsapp.awaiting_reply', true);
    $this->getJson(automationStateUrl($answered), automationStateToken())
        ->assertJsonPath('whatsapp.awaiting_reply', false);
});

test('reading the automation state writes nothing', function () {
    $lead = automationStateLead([
        'trial_started_at' => '2026-09-20',
        'trial_ends_at' => '2026-10-05',
        'next_follow_up_at' => '2026-09-28 09:00',
        'last_contacted_at' => '2026-09-28 11:00:00',
    ]);
    automationStateMessage($lead, LeadActivity::DIRECTION_OUTBOUND, '2026-09-28 10:00:00');
    automationStateMessage($lead, LeadActivity::DIRECTION_INBOUND, '2026-09-28 11:00:00');

    $leadRow = fn () => (array) DB::table('leads')->where('id', $lead->id)->first();
    $activityRows = fn () => DB::table('lead_activities')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
    $leadBefore = $leadRow();
    $activitiesBefore = $activityRows();

    $this->travel(5)->minutes();

    DB::enableQueryLog();
    $this->getJson(automationStateUrl($lead), automationStateToken())->assertOk();
    $this->getJson(automationStateUrl($lead), automationStateToken())->assertOk();
    $queries = collect(DB::getQueryLog())->pluck('query');
    DB::disableQueryLog();

    expect($leadRow())->toBe($leadBefore)
        ->and($activityRows())->toBe($activitiesBefore)
        ->and($queries->filter(fn (string $sql) => preg_match('/^\s*(insert|update|delete|replace)\b/i', $sql) === 1)
            ->reject(fn (string $sql) => str_contains($sql, 'cache'))
            ->values()->all())->toBe([]);
});

test('the automation state uses a constant number of queries', function () {
    $lead = automationStateLead();

    foreach (range(1, 10) as $minute) {
        automationStateMessage($lead, $minute % 2 ? 'inbound' : 'outbound', sprintf('2026-09-28 10:%02d:00', $minute));
    }

    DB::enableQueryLog();
    $this->getJson(automationStateUrl($lead), automationStateToken())->assertOk();
    $leadQueries = collect(DB::getQueryLog())->pluck('query')
        ->filter(fn (string $sql) => str_contains($sql, 'leads') || str_contains($sql, 'lead_activities'));
    DB::disableQueryLog();

    // Resolver o lead, verificar a espera de resposta e buscar as duas últimas mensagens.
    expect($leadQueries)->toHaveCount(3);
});
