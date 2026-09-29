<?php

use App\Http\Controllers\LeadController;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\User;
use App\Services\CompanyWhatsappSettings;
use App\Services\WhatsappBusinessHours;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

function leadSlaLead(array $overrides = []): Lead
{
    return Lead::query()->create(array_merge([
        'company_name' => 'Lead com SLA',
        'product' => 'site',
        'status' => 'contacted',
        'whatsapp' => '5551995179173',
    ], $overrides));
}

/** Mensagem da conversa gravada no instante informado (created_at define a ordem da conversa). */
function leadSlaMessage(Lead $lead, string $direction, string $at, array $overrides = []): LeadActivity
{
    $activity = $lead->activities()->create(array_merge([
        'type' => 'whatsapp',
        'direction' => $direction,
        'contacted_at' => $at,
        'description' => $direction === LeadActivity::DIRECTION_INBOUND ? 'Mensagem do cliente.' : 'Mensagem da empresa.',
        'provider_message_id' => 'msg_'.fake()->unique()->uuid(),
        'message_status' => $direction === LeadActivity::DIRECTION_OUTBOUND ? LeadActivity::MESSAGE_STATUS_SERVER : null,
    ], $overrides));

    $activity->forceFill(['created_at' => $at, 'updated_at' => $at])->save();

    return $activity->refresh();
}

/** Lead com uma inbound sem resposta no instante informado. */
function leadSlaWaitingLead(string $since, array $overrides = []): Lead
{
    $lead = leadSlaLead($overrides);
    leadSlaMessage($lead, LeadActivity::DIRECTION_INBOUND, $since);

    return $lead;
}

/**
 * Níveis de SLA em que o banco classifica o lead agora. Um lead na fila está em exatamente um
 * nível; fora da fila, em nenhum.
 *
 * @return list<string>
 */
function leadSlaLevels(Lead $lead): array
{
    return array_values(array_filter(
        array_keys(Lead::WHATSAPP_REPLY_SLA),
        fn (string $level) => Lead::query()->whatsappReplySla($level)->whereKey($lead->id)->exists(),
    ));
}

/** @return list<int> */
function leadSlaIds(string $level): array
{
    return Lead::query()->whatsappReplyQueue()->whatsappReplySla($level)->pluck('id')->all();
}

function leadSlaToken(): array
{
    return ['Authorization' => 'Bearer '.config('services.ab_prospect.token')];
}

beforeEach(function () {
    Carbon::setTestNow('2026-09-28 15:00:00');
    // Atendimento 24 h: aqui valem só os limites e o ciclo da espera. O horário de atendimento
    // (relógio parado fora do expediente) é coberto em LeadWhatsappBusinessHoursSlaTest.
    app(WhatsappBusinessHours::class)->save([
        'timezone' => 'UTC',
        'days' => array_fill(1, 7, ['active' => true, 'opens_at' => '00:00', 'closes_at' => '24:00']),
    ]);
});

afterEach(function () {
    Carbon::setTestNow();
});

test('the SLA levels are the four agreed thresholds', function () {
    expect(array_map(fn (array $level) => $level['from_minutes'], Lead::WHATSAPP_REPLY_SLA))->toBe([
        'normal' => 0,
        'attention' => 15,
        'urgent' => 30,
        'critical' => 60,
    ]);
});

test('the waiting time is classified at the exact boundaries', function (string $since, string $level) {
    $lead = leadSlaWaitingLead($since);

    expect(leadSlaLevels($lead))->toBe([$level]);
})->with([
    'just arrived' => ['2026-09-28 15:00:00', 'normal'],
    '14m59s' => ['2026-09-28 14:45:01', 'normal'],
    'exactly 15m' => ['2026-09-28 14:45:00', 'attention'],
    '29m59s' => ['2026-09-28 14:30:01', 'attention'],
    'exactly 30m' => ['2026-09-28 14:30:00', 'urgent'],
    '59m59s' => ['2026-09-28 14:00:01', 'urgent'],
    'exactly 60m' => ['2026-09-28 14:00:00', 'critical'],
    'several hours' => ['2026-09-28 09:12:00', 'critical'],
    'several days' => ['2026-09-20 09:00:00', 'critical'],
]);

test('fractions of a second on the clock do not move the boundary', function () {
    $lead = leadSlaWaitingLead('2026-09-28 14:45:01');

    Carbon::setTestNow('2026-09-28 15:00:00.999999');
    expect(leadSlaLevels($lead))->toBe(['normal']);

    Carbon::setTestNow('2026-09-28 15:00:01');
    expect(leadSlaLevels($lead))->toBe(['attention']);
});

test('the level follows the clock without any change to the lead', function () {
    $lead = leadSlaWaitingLead('2026-09-28 08:00:00');

    foreach ([
        '2026-09-28 08:00:00' => 'normal',
        '2026-09-28 08:14:59' => 'normal',
        '2026-09-28 08:15:00' => 'attention',
        '2026-09-28 08:30:00' => 'urgent',
        '2026-09-28 09:00:00' => 'critical',
    ] as $now => $level) {
        Carbon::setTestNow($now);

        expect(leadSlaLevels($lead))->toBe([$level]);
    }
});

test('taking the lead does not restart the SLA', function () {
    $lead = leadSlaWaitingLead('2026-09-28 14:00:00');

    $this->actingAs(User::factory()->create())
        ->patch(route('leads.assign-to-me', $lead))
        ->assertSessionHasNoErrors();

    expect($lead->refresh()->user_id)->not->toBeNull()
        ->and(leadSlaLevels($lead))->toBe(['critical']);
});

test('transferring the owner does not restart the SLA', function () {
    $from = User::factory()->create(['name' => 'A']);
    $to = User::factory()->create(['name' => 'B']);
    $lead = leadSlaWaitingLead('2026-09-28 08:00:00', ['user_id' => $from->id]);

    Carbon::setTestNow('2026-09-28 08:45:00');
    $this->actingAs($from)
        ->patch(route('leads.bulk'), ['ids' => [$lead->id], 'user_id' => $to->id])
        ->assertSessionHasNoErrors();

    Carbon::setTestNow('2026-09-28 09:00:00');

    expect($lead->refresh()->user_id)->toBe($to->id)
        // A nota de atribuição (CRM-WA-12) não responde nem reinicia a espera.
        ->and($lead->activities()->where('type', 'note')->count())->toBe(1)
        ->and(leadSlaLevels($lead))->toBe(['critical'])
        ->and(Lead::query()->whatsappReplyQueue()->sole()->awaiting_reply_since->toDateTimeString())->toBe('2026-09-28 08:00:00');
});

test('reading the inbound does not restart the SLA', function () {
    $lead = leadSlaLead();
    $inbound = leadSlaMessage($lead, LeadActivity::DIRECTION_INBOUND, '2026-09-28 08:00:00');

    Carbon::setTestNow('2026-09-28 08:05:00');
    $this->actingAs(User::factory()->create())
        ->postJson(route('leads.whatsapp.read', $lead), ['up_to_id' => $inbound->id])
        ->assertOk();

    Carbon::setTestNow('2026-09-28 08:30:00');

    expect($inbound->refresh()->read_at)->not->toBeNull()
        ->and(leadSlaLevels($lead))->toBe(['urgent']);
});

test('notes, calls, e-mails and follow-ups do not restart the SLA', function () {
    $lead = leadSlaWaitingLead('2026-09-28 14:00:00');
    $this->actingAs(User::factory()->create());

    foreach (['note', 'call', 'email'] as $type) {
        $this->post(route('leads.activities.store', $lead), [
            'type' => $type,
            'description' => "Registro {$type}.",
            'contacted_at' => '2026-09-28T14:50',
        ])->assertSessionHasNoErrors();
    }

    $this->patch(route('leads.follow-up', $lead), ['next_follow_up_at' => '2026-09-29 10:00'])
        ->assertSessionHasNoErrors();

    expect($lead->refresh()->next_follow_up_at)->not->toBeNull()
        ->and($lead->activities()->where('type', '!=', 'whatsapp')->count())->toBe(4)
        ->and(leadSlaLevels($lead))->toBe(['critical']);
});

test('an outbound from the CRM ends the SLA', function () {
    $lead = leadSlaWaitingLead('2026-09-28 14:00:00');

    $this->postJson("/api/prospects/{$lead->id}/whatsapp/log", [
        'message_id' => 'OUT-CRM-SLA',
        'status' => 'PENDING',
        'message' => 'Olá, como posso ajudar?',
    ], leadSlaToken())->assertCreated();

    expect(leadSlaLevels($lead))->toBe([]);
});

test('an external outbound ends the SLA', function () {
    app(CompanyWhatsappSettings::class)->save([
        'enabled' => true,
        'number' => '5551998931325',
        'provider' => CompanyWhatsappSettings::PROVIDER_WAHA,
        'session' => 'vetoros1-1',
    ]);
    $lead = leadSlaWaitingLead('2026-09-28 14:00:00');

    $this->postJson('/api/whatsapp/outbound-external', [
        'session' => 'vetoros1-1',
        'provider_message_id' => 'OUT-EXT-SLA',
        'to' => '555195179173@s.whatsapp.net',
        'message' => 'Respondi pelo celular.',
        'status' => 'SERVER',
    ], leadSlaToken())->assertCreated();

    expect(leadSlaLevels($lead))->toBe([]);
});

test('a new inbound after the reply starts a new SLA at normal', function () {
    $lead = leadSlaLead();
    leadSlaMessage($lead, LeadActivity::DIRECTION_INBOUND, '2026-09-28 08:00:00');
    leadSlaMessage($lead, LeadActivity::DIRECTION_OUTBOUND, '2026-09-28 09:10:00');

    Carbon::setTestNow('2026-09-28 09:20:00');
    leadSlaMessage($lead, LeadActivity::DIRECTION_INBOUND, '2026-09-28 09:20:00');

    expect(leadSlaLevels($lead))->toBe(['normal']);

    Carbon::setTestNow('2026-09-28 09:35:00');

    expect(leadSlaLevels($lead))->toBe(['attention']);
});

test('converted and lost leads get an SLA like any other', function (string $status) {
    $lead = leadSlaWaitingLead('2026-09-28 14:20:00', [
        'status' => $status,
        'lost_reason' => $status === 'lost' ? 'price' : null,
    ]);

    expect(leadSlaLevels($lead))->toBe(['urgent']);
})->with(['converted', 'lost']);

test('the sla filter returns only the leads in that level', function (string $level, string $expected) {
    $leads = [
        'normal' => leadSlaWaitingLead('2026-09-28 14:50:00', ['company_name' => 'Normal', 'whatsapp' => '5551900000001']),
        'attention' => leadSlaWaitingLead('2026-09-28 14:40:00', ['company_name' => 'Atenção', 'whatsapp' => '5551900000002']),
        'urgent' => leadSlaWaitingLead('2026-09-28 14:20:00', ['company_name' => 'Urgente', 'whatsapp' => '5551900000003']),
        'critical' => leadSlaWaitingLead('2026-09-28 13:00:00', ['company_name' => 'Crítico', 'whatsapp' => '5551900000004']),
    ];

    $this->actingAs(User::factory()->create())
        ->get(route('leads.awaiting-reply', ['sla' => $level]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.sla', $level)
            ->where('leads.total', 1)
            ->where('leads.data.0.id', $leads[$expected]->id));
})->with([
    ['normal', 'normal'],
    ['attention', 'attention'],
    ['urgent', 'urgent'],
    ['critical', 'critical'],
]);

test('an unknown sla value does not filter the queue', function () {
    leadSlaWaitingLead('2026-09-28 14:50:00', ['whatsapp' => '5551900000001']);
    leadSlaWaitingLead('2026-09-28 13:00:00', ['whatsapp' => '5551900000002']);

    $this->actingAs(User::factory()->create())
        ->get(route('leads.awaiting-reply', ['sla' => 'vip']))
        ->assertInertia(fn (Assert $page) => $page->where('leads.total', 2));
});

test('the sla filter combines with owner, status, product and search', function () {
    $me = User::factory()->create();
    $other = User::factory()->create();

    $target = leadSlaWaitingLead('2026-09-28 13:00:00', [
        'company_name' => 'Padaria Alvo', 'user_id' => $me->id, 'status' => 'interested', 'product' => 'vetoros', 'whatsapp' => '5551900000001',
    ]);
    // Cada um difere do alvo em um único critério.
    leadSlaWaitingLead('2026-09-28 13:00:00', ['company_name' => 'Padaria Outro dono', 'user_id' => $other->id, 'status' => 'interested', 'product' => 'vetoros', 'whatsapp' => '5551900000002']);
    leadSlaWaitingLead('2026-09-28 13:00:00', ['company_name' => 'Padaria Outro status', 'user_id' => $me->id, 'status' => 'new', 'product' => 'vetoros', 'whatsapp' => '5551900000003']);
    leadSlaWaitingLead('2026-09-28 13:00:00', ['company_name' => 'Padaria Outro produto', 'user_id' => $me->id, 'status' => 'interested', 'product' => 'site', 'whatsapp' => '5551900000004']);
    leadSlaWaitingLead('2026-09-28 13:00:00', ['company_name' => 'Mercado Outra busca', 'user_id' => $me->id, 'status' => 'interested', 'product' => 'vetoros', 'whatsapp' => '5551900000005']);
    leadSlaWaitingLead('2026-09-28 14:50:00', ['company_name' => 'Padaria Outro SLA', 'user_id' => $me->id, 'status' => 'interested', 'product' => 'vetoros', 'whatsapp' => '5551900000006']);

    $this->actingAs($me);

    $ids = fn (array $query) => collect($this->get(route('leads.awaiting-reply', $query))->viewData('page')['props']['leads']['data'])
        ->pluck('id')->sort()->values()->all();

    expect($ids(['sla' => 'critical']))->toHaveCount(5)
        ->and($ids(['sla' => 'critical', 'owner' => 'mine']))->toHaveCount(4)
        ->and($ids(['sla' => 'critical', 'status' => 'interested']))->toHaveCount(4)
        ->and($ids(['sla' => 'critical', 'product' => 'vetoros']))->toHaveCount(4)
        ->and($ids(['sla' => 'critical', 'search' => 'Padaria']))->toHaveCount(4)
        ->and($ids(['sla' => 'critical', 'owner' => 'mine', 'status' => 'interested', 'product' => 'vetoros', 'search' => 'Padaria']))->toBe([$target->id]);
});

test('pagination counts only the leads in the filtered level', function () {
    $perPage = LeadController::AWAITING_REPLY_PER_PAGE;

    // Os normais esperam menos, mas são criados primeiro: a paginação não pode cortá-los antes do filtro.
    foreach (range(1, $perPage + 5) as $i) {
        leadSlaWaitingLead('2026-09-28 14:55:00', ['whatsapp' => '55519'.str_pad((string) $i, 8, '0', STR_PAD_LEFT)]);
    }

    $critical = collect(range(1, $perPage + 2))->map(fn (int $i) => leadSlaWaitingLead(
        Carbon::parse('2026-09-28 12:00:00')->addMinutes($i)->toDateTimeString(),
        ['whatsapp' => '55518'.str_pad((string) $i, 8, '0', STR_PAD_LEFT)],
    ));

    $this->actingAs(User::factory()->create());

    $first = $this->get(route('leads.awaiting-reply', ['sla' => 'critical']))->viewData('page')['props']['leads'];
    $second = $this->get(route('leads.awaiting-reply', ['sla' => 'critical', 'page' => 2]))->viewData('page')['props']['leads'];

    $ids = collect($first['data'])->merge($second['data'])->pluck('id');

    expect($first['total'])->toBe($perPage + 2)
        ->and($first['last_page'])->toBe(2)
        ->and($second['data'])->toHaveCount(2)
        ->and($ids->unique()->count())->toBe($perPage + 2)
        // Ordem preservada: quem espera há mais tempo primeiro.
        ->and($ids->all())->toBe($critical->pluck('id')->all());
});

test('the critical counter matches the critical queue, on the dashboard and the leads page', function () {
    leadSlaWaitingLead('2026-09-28 13:00:00', ['whatsapp' => '5551900000001']);
    leadSlaWaitingLead('2026-09-28 14:00:00', ['whatsapp' => '5551900000002']);
    leadSlaWaitingLead('2026-09-28 14:00:01', ['whatsapp' => '5551900000003']);
    leadSlaWaitingLead('2026-09-28 14:59:00', ['whatsapp' => '5551900000004']);

    $answered = leadSlaLead(['whatsapp' => '5551900000005']);
    leadSlaMessage($answered, LeadActivity::DIRECTION_INBOUND, '2026-09-28 10:00:00');
    leadSlaMessage($answered, LeadActivity::DIRECTION_OUTBOUND, '2026-09-28 10:05:00');

    $deleted = leadSlaWaitingLead('2026-09-28 10:00:00', ['whatsapp' => '5551900000006']);
    $deleted->delete();

    leadSlaLead(['whatsapp' => '5551900000007'])->activities()->create(['type' => 'note', 'description' => 'Sem mensagens.']);
    leadSlaMessage(leadSlaLead(['whatsapp' => '5551900000008']), LeadActivity::DIRECTION_OUTBOUND, '2026-09-28 10:00:00');

    $this->actingAs(User::factory()->create());

    $queue = $this->get(route('leads.awaiting-reply', ['sla' => 'critical']))->viewData('page')['props']['leads'];

    expect($queue['total'])->toBe(2)
        ->and(Lead::query()->whatsappReplySla('critical')->count())->toBe(2);

    $this->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('metrics.awaiting_reply', 4)
            ->where('metrics.awaiting_reply_critical', 2));

    $this->get(route('leads.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('awaitingReplyTotal', 4)
            ->where('awaitingReplyCritical', 2));
});

test('leads outside the queue never get an SLA level', function () {
    $noMessages = leadSlaLead(['whatsapp' => '5551900000001']);
    $noMessages->activities()->create(['type' => 'note', 'description' => 'Nota.']);

    $onlyOutbound = leadSlaLead(['whatsapp' => '5551900000002']);
    leadSlaMessage($onlyOutbound, LeadActivity::DIRECTION_OUTBOUND, '2026-09-28 10:00:00');

    $answered = leadSlaLead(['whatsapp' => '5551900000003']);
    leadSlaMessage($answered, LeadActivity::DIRECTION_INBOUND, '2026-09-28 10:00:00');
    leadSlaMessage($answered, LeadActivity::DIRECTION_OUTBOUND, '2026-09-28 10:05:00');

    $deleted = leadSlaWaitingLead('2026-09-28 10:00:00', ['whatsapp' => '5551900000004']);
    $deleted->delete();

    foreach ([$noMessages, $onlyOutbound, $answered] as $lead) {
        expect(leadSlaLevels($lead))->toBe([]);
    }

    foreach (array_keys(Lead::WHATSAPP_REPLY_SLA) as $level) {
        expect(Lead::query()->whatsappReplySla($level)->count())->toBe(0);
    }
});

test('consecutive inbound messages keep the wait at the first one and the excerpt at the latest', function () {
    $lead = leadSlaLead();
    leadSlaMessage($lead, LeadActivity::DIRECTION_INBOUND, '2026-09-28 13:55:00', ['description' => 'Oi']);
    leadSlaMessage($lead, LeadActivity::DIRECTION_INBOUND, '2026-09-28 14:55:00', ['description' => 'Alguém aí?']);

    $this->actingAs(User::factory()->create())
        ->get(route('leads.awaiting-reply', ['sla' => 'critical']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('leads.total', 1)
            ->where('leads.data.0.awaiting_reply_since', '2026-09-28T13:55:00.000000Z')
            ->where('leads.data.0.last_inbound_at', '2026-09-28T14:55:00.000000Z')
            ->where('leads.data.0.last_inbound_message', 'Alguém aí?'));

    expect(leadSlaLevels($lead))->toBe(['critical']);
});

test('filtering by sla keeps the longest waiting first', function () {
    $newer = leadSlaWaitingLead('2026-09-28 12:00:00', ['whatsapp' => '5551900000001']);
    $older = leadSlaWaitingLead('2026-09-28 09:00:00', ['whatsapp' => '5551900000002']);
    $middle = leadSlaWaitingLead('2026-09-28 10:30:00', ['whatsapp' => '5551900000003']);

    expect(leadSlaIds('critical'))->toBe([$older->id, $middle->id, $newer->id]);
});

test('the queue exposes the same thresholds the backend uses, in UTC ISO dates', function () {
    leadSlaWaitingLead('2026-09-28 14:45:00');

    $this->actingAs(User::factory()->create())
        ->get(route('leads.awaiting-reply'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('slaLevels', Lead::WHATSAPP_REPLY_SLA)
            ->where('slaLevels.critical.from_minutes', 60)
            // Z = UTC: o navegador calcula now - awaiting_reply_since no mesmo instante que o banco.
            ->where('leads.data.0.awaiting_reply_since', '2026-09-28T14:45:00.000000Z'));
});
