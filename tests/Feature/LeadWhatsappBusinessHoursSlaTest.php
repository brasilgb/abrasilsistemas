<?php

use App\Http\Controllers\LeadController;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\Setting;
use App\Models\User;
use App\Services\WhatsappBusinessHours;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * SLA por horário de atendimento (CRM-WA-14). A jornada dos testes é explícita (bhSlaWeek):
 * segunda a sexta 08:00–18:00, sábado e domingo fechados, America/Sao_Paulo (UTC-3). Os horários
 * dos testes são locais (Brasília) e convertidos para UTC, que é como o app grava.
 * 2026-09-28 é segunda-feira; 2026-10-02 sexta; 2026-10-03 sábado; 2026-10-04 domingo.
 */

/** Horário de Brasília → UTC (Y-m-d H:i:s), como gravado no banco. */
function bhSlaUtc(string $local): string
{
    return CarbonImmutable::parse($local, 'America/Sao_Paulo')->utc()->toDateTimeString();
}

function bhSlaNow(string $local): void
{
    Carbon::setTestNow(CarbonImmutable::parse($local, 'America/Sao_Paulo')->utc());
}

function bhSlaMinutes(string $sinceLocal, string $nowLocal): int
{
    return app(WhatsappBusinessHours::class)->elapsedMinutes(
        CarbonImmutable::parse($sinceLocal, 'America/Sao_Paulo'),
        CarbonImmutable::parse($nowLocal, 'America/Sao_Paulo'),
    );
}

/** Jornada de teste; $days troca dias específicos (ex.: sábado aberto). */
function bhSlaWeek(array $days = [], string $timezone = 'America/Sao_Paulo'): void
{
    $weekday = ['active' => true, 'opens_at' => '08:00', 'closes_at' => '18:00'];
    $closed = ['active' => false, 'opens_at' => '08:00', 'closes_at' => '12:00'];

    app(WhatsappBusinessHours::class)->save([
        'timezone' => $timezone,
        'days' => array_replace([1 => $weekday, 2 => $weekday, 3 => $weekday, 4 => $weekday, 5 => $weekday, 6 => $closed, 7 => $closed], $days),
    ]);
}

function bhSlaLead(array $overrides = []): Lead
{
    return Lead::query()->create(array_merge([
        'company_name' => 'Lead horário comercial',
        'product' => 'site',
        'status' => 'contacted',
        'whatsapp' => '5551995179173',
    ], $overrides));
}

function bhSlaMessage(Lead $lead, string $direction, string $local): LeadActivity
{
    $at = bhSlaUtc($local);
    $activity = $lead->activities()->create([
        'type' => 'whatsapp',
        'direction' => $direction,
        'contacted_at' => $at,
        'description' => $direction === LeadActivity::DIRECTION_INBOUND ? 'Mensagem do cliente.' : 'Mensagem da empresa.',
        'provider_message_id' => 'msg_'.fake()->unique()->uuid(),
        'message_status' => $direction === LeadActivity::DIRECTION_OUTBOUND ? LeadActivity::MESSAGE_STATUS_SERVER : null,
    ]);

    $activity->forceFill(['created_at' => $at, 'updated_at' => $at])->save();

    return $activity->refresh();
}

function bhSlaWaitingLead(string $sinceLocal, array $overrides = []): Lead
{
    $lead = bhSlaLead($overrides);
    bhSlaMessage($lead, LeadActivity::DIRECTION_INBOUND, $sinceLocal);

    return $lead;
}

/** @return list<string> */
function bhSlaLevels(Lead $lead): array
{
    return array_values(array_filter(
        array_keys(Lead::WHATSAPP_REPLY_SLA),
        fn (string $level) => Lead::query()->whatsappReplySla($level)->whereKey($lead->id)->exists(),
    ));
}

beforeEach(function () {
    bhSlaWeek();
});

afterEach(function () {
    Carbon::setTestNow();
});

test('without saved settings the documented fallback is Monday to Friday, 08:00 to 18:00 in Brasília time', function () {
    Setting::query()->delete();
    $hours = new WhatsappBusinessHours;

    expect($hours->current()['configured'])->toBeFalse()
        ->and($hours->current()['timezone'])->toBe('America/Sao_Paulo')
        ->and(array_map(fn (array $day) => $day['active'], $hours->current()['days']))
        ->toBe([1 => true, 2 => true, 3 => true, 4 => true, 5 => true, 6 => false, 7 => false])
        // A tela avisa que é o horário padrão, não o horário real salvo.
        ->and($hours->description())->toBe('Seg a Sex, 08:00–18:00 (America/Sao_Paulo, horário padrão)')
        // O fuso global do app não muda: só o cálculo do expediente usa o fuso operacional.
        ->and(config('app.timezone'))->toBe('UTC');
});

test('the saved week is used and described', function () {
    bhSlaWeek([6 => ['active' => true, 'opens_at' => '08:00', 'closes_at' => '12:00']]);

    expect(app(WhatsappBusinessHours::class)->current()['configured'])->toBeTrue()
        ->and(app(WhatsappBusinessHours::class)->description())->toBe('Seg a Sex, 08:00–18:00; Sáb, 08:00–12:00 (America/Sao_Paulo)');
});

test('business minutes are counted only inside the opening hours', function (string $since, string $now, int $minutes) {
    expect(bhSlaMinutes($since, $now))->toBe($minutes);
})->with([
    'start of the business day' => ['2026-09-28 08:00', '2026-09-28 08:00', 0],
    'during business hours' => ['2026-09-28 09:00', '2026-09-28 09:40', 40],
    'before opening, at the opening' => ['2026-09-28 07:30', '2026-09-28 08:00', 0],
    'before opening, 08:15' => ['2026-09-28 07:30', '2026-09-28 08:15', 15],
    'before opening, still closed' => ['2026-09-28 06:00', '2026-09-28 07:59', 0],
    'crossing the opening' => ['2026-09-28 07:50', '2026-09-28 08:05', 5],
    'exactly at the opening' => ['2026-09-28 08:00', '2026-09-28 08:15', 15],
    'after closing, next morning' => ['2026-09-28 18:30', '2026-09-29 08:05', 5],
    'exactly at the closing' => ['2026-09-28 18:00', '2026-09-29 08:00', 0],
    'crossing the closing, 18:00' => ['2026-09-28 17:55', '2026-09-28 18:00', 5],
    'crossing the closing, 23:00' => ['2026-09-28 17:55', '2026-09-28 23:00', 5],
    'monday to tuesday' => ['2026-09-28 17:55', '2026-09-29 08:10', 15],
    'crossing midnight' => ['2026-09-28 17:00', '2026-09-29 09:00', 120],
    'friday to monday opening' => ['2026-10-02 17:55', '2026-10-05 08:00', 5],
    'friday to monday 08:10' => ['2026-10-02 17:55', '2026-10-05 08:10', 15],
    'saturday closed' => ['2026-10-02 17:55', '2026-10-03 12:00', 5],
    'sunday closed' => ['2026-10-04 09:00', '2026-10-04 23:00', 0],
    'weekend message, monday 08:15' => ['2026-10-03 10:00', '2026-10-05 08:15', 15],
    'several days' => ['2026-09-28 08:00', '2026-10-01 08:00', 1800],
    'a whole week' => ['2026-09-28 08:00', '2026-10-05 08:00', 3000],
    'start in the future' => ['2026-09-28 10:00', '2026-09-28 09:00', 0],
]);

test('an open saturday counts and sunday stays closed', function () {
    bhSlaWeek([6 => ['active' => true, 'opens_at' => '08:00', 'closes_at' => '12:00']]);

    expect(bhSlaMinutes('2026-10-02 17:55', '2026-10-03 08:10'))->toBe(15)
        ->and(bhSlaMinutes('2026-10-02 17:55', '2026-10-03 12:00'))->toBe(245)
        // Sábado 12:00 até domingo 23:59: nada acumula.
        ->and(bhSlaMinutes('2026-10-03 11:50', '2026-10-04 23:59'))->toBe(10)
        ->and(bhSlaMinutes('2026-10-03 11:50', '2026-10-05 08:05'))->toBe(15);

    $lead = bhSlaWaitingLead('2026-10-02 17:55');

    bhSlaNow('2026-10-03 08:09:59');
    expect(bhSlaLevels($lead))->toBe(['normal']);

    bhSlaNow('2026-10-03 08:10');
    expect(bhSlaLevels($lead))->toBe(['attention']);
});

test('the SLA freezes overnight: 17:55 to 18:00 is 5 minutes, and at 08:10 it reaches attention', function () {
    $lead = bhSlaWaitingLead('2026-09-28 17:55');

    foreach ([
        '2026-09-28 18:00' => [5, 'normal'],
        '2026-09-28 23:00' => [5, 'normal'],
        '2026-09-29 03:00' => [5, 'normal'],
        '2026-09-29 08:00' => [5, 'normal'],
        '2026-09-29 08:10' => [15, 'attention'],
    ] as $now => [$minutes, $level]) {
        bhSlaNow($now);

        expect(bhSlaMinutes('2026-09-28 17:55', $now))->toBe($minutes)
            ->and(bhSlaLevels($lead))->toBe([$level]);
    }
});

test('the boundaries are exact inside a single business day', function (string $now, string $level) {
    $lead = bhSlaWaitingLead('2026-09-28 08:00');
    bhSlaNow($now);

    expect(bhSlaLevels($lead))->toBe([$level]);
})->with([
    'start' => ['2026-09-28 08:00:00', 'normal'],
    '14:59' => ['2026-09-28 08:14:59', 'normal'],
    '15:00' => ['2026-09-28 08:15:00', 'attention'],
    '29:59' => ['2026-09-28 08:29:59', 'attention'],
    '30:00' => ['2026-09-28 08:30:00', 'urgent'],
    '59:59' => ['2026-09-28 08:59:59', 'urgent'],
    '60:00' => ['2026-09-28 09:00:00', 'critical'],
]);

test('the boundaries use accumulated business time across the night', function (string $now, string $level) {
    // 10 min de expediente na segunda; o restante conta a partir das 08:00 de terça.
    $lead = bhSlaWaitingLead('2026-09-28 17:50');
    bhSlaNow($now);

    expect(bhSlaLevels($lead))->toBe([$level]);
})->with([
    '14:59' => ['2026-09-29 08:04:59', 'normal'],
    '15:00' => ['2026-09-29 08:05:00', 'attention'],
    '29:59' => ['2026-09-29 08:19:59', 'attention'],
    '30:00' => ['2026-09-29 08:20:00', 'urgent'],
    '59:59' => ['2026-09-29 08:49:59', 'urgent'],
    '60:00' => ['2026-09-29 08:50:00', 'critical'],
]);

test('fractions of a second on the clock do not move the boundary', function () {
    $lead = bhSlaWaitingLead('2026-09-28 17:50');

    Carbon::setTestNow(CarbonImmutable::parse('2026-09-29 08:04:59.999999', 'America/Sao_Paulo')->utc());
    expect(bhSlaLevels($lead))->toBe(['normal']);

    Carbon::setTestNow(CarbonImmutable::parse('2026-09-29 08:05:00.000001', 'America/Sao_Paulo')->utc());
    expect(bhSlaLevels($lead))->toBe(['attention']);
});

test('a message received outside business hours stays normal until the opening', function () {
    // Sexta 18:30 até segunda 07:59: mais de 61 h de relógio, zero de expediente.
    $lead = bhSlaWaitingLead('2026-10-02 18:30');

    bhSlaNow('2026-10-05 07:59');
    expect(bhSlaLevels($lead))->toBe(['normal']);

    bhSlaNow('2026-10-05 08:15');
    expect(bhSlaLevels($lead))->toBe(['attention']);

    bhSlaNow('2026-10-05 09:00');
    expect(bhSlaLevels($lead))->toBe(['critical']);
});

test('a friday message keeps its minutes over the weekend', function () {
    $lead = bhSlaWaitingLead('2026-10-02 17:40');

    bhSlaNow('2026-10-03 12:00');
    expect(bhSlaLevels($lead))->toBe(['attention']);

    bhSlaNow('2026-10-05 08:09:59');
    expect(bhSlaLevels($lead))->toBe(['attention']);

    bhSlaNow('2026-10-05 08:10');
    expect(bhSlaLevels($lead))->toBe(['urgent']);
});

test('the database filter agrees with the business-time function at every instant', function () {
    // Mesmo sentido do scope: começar até o corte <=> ter atingido os minutos úteis.
    bhSlaWeek([6 => ['active' => true, 'opens_at' => '08:00', 'closes_at' => '12:00']]);
    $hours = app(WhatsappBusinessHours::class);

    foreach (['2026-09-29 08:03:17', '2026-09-29 12:00:00', '2026-09-29 19:30:00', '2026-10-03 11:00:00', '2026-10-04 15:00:00', '2026-10-05 08:00:00'] as $nowLocal) {
        $now = CarbonImmutable::parse($nowLocal, 'America/Sao_Paulo');

        foreach ([15, 30, 60] as $minutes) {
            $cutoff = $hours->cutoff($now, $minutes);

            for ($since = $now->subDays(4); $since <= $now; $since = $since->addMinutes(17)->addSeconds(13)) {
                $reached = $hours->elapsedSeconds($since, $now) >= $minutes * 60;

                expect($since <= $cutoff)->toBe($reached, "{$since} / {$nowLocal} / {$minutes}");
            }
        }
    }
});

test('an outbound during business hours ends the wait and a new inbound restarts it', function () {
    $lead = bhSlaWaitingLead('2026-09-28 17:00');
    bhSlaMessage($lead, LeadActivity::DIRECTION_OUTBOUND, '2026-09-29 08:02');

    bhSlaNow('2026-09-29 08:02');
    expect(bhSlaLevels($lead))->toBe([]);

    bhSlaMessage($lead, LeadActivity::DIRECTION_INBOUND, '2026-09-29 08:03');

    bhSlaNow('2026-09-29 08:17:59');
    expect(bhSlaLevels($lead))->toBe(['normal']);

    bhSlaNow('2026-09-29 08:18');
    expect(bhSlaLevels($lead))->toBe(['attention']);
});

test('an outbound outside business hours ends the wait, and a new inbound at night enters the queue at zero', function () {
    $lead = bhSlaWaitingLead('2026-09-28 17:00');
    bhSlaMessage($lead, LeadActivity::DIRECTION_OUTBOUND, '2026-09-28 19:00');

    bhSlaNow('2026-09-28 19:00');
    expect(bhSlaLevels($lead))->toBe([]);

    bhSlaMessage($lead, LeadActivity::DIRECTION_INBOUND, '2026-09-28 19:10');
    bhSlaNow('2026-09-28 22:00');

    // Entra na fila na hora, com tempo útil zero e Normal até a abertura.
    $this->actingAs(User::factory()->create())
        ->get(route('leads.awaiting-reply'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('leads.total', 1)
            ->where('leads.data.0.awaiting_reply_since', '2026-09-28T22:10:00.000000Z')
            ->where('leads.data.0.awaiting_reply_business_seconds', 0));

    expect(bhSlaLevels($lead))->toBe(['normal']);

    bhSlaNow('2026-09-29 08:14:59');
    expect(bhSlaLevels($lead))->toBe(['normal']);

    bhSlaNow('2026-09-29 08:15');
    expect(bhSlaLevels($lead))->toBe(['attention']);
});

test('two unanswered inbound messages count from the first one', function () {
    $lead = bhSlaWaitingLead('2026-09-28 17:50');
    bhSlaMessage($lead, LeadActivity::DIRECTION_INBOUND, '2026-09-29 08:04');

    bhSlaNow('2026-09-29 08:05');
    expect(bhSlaLevels($lead))->toBe(['attention']);
});

test('reading, taking and transferring the lead do not change the business SLA', function () {
    $from = User::factory()->create(['name' => 'A']);
    $to = User::factory()->create(['name' => 'B']);
    $lead = bhSlaLead();
    $inbound = bhSlaMessage($lead, LeadActivity::DIRECTION_INBOUND, '2026-09-28 17:50');

    bhSlaNow('2026-09-29 08:01');
    $this->actingAs($from)
        ->postJson(route('leads.whatsapp.read', $lead), ['up_to_id' => $inbound->id])
        ->assertOk();

    bhSlaNow('2026-09-29 08:02');
    $this->patch(route('leads.assign-to-me', $lead))->assertSessionHasNoErrors();

    bhSlaNow('2026-09-29 08:03');
    $this->patch(route('leads.bulk'), ['ids' => [$lead->id], 'user_id' => $to->id])->assertSessionHasNoErrors();

    bhSlaNow('2026-09-29 08:49:59');
    expect($inbound->refresh()->read_at)->not->toBeNull()
        ->and($lead->refresh()->user_id)->toBe($to->id)
        ->and(bhSlaLevels($lead))->toBe(['urgent']);

    bhSlaNow('2026-09-29 08:50');
    expect(bhSlaLevels($lead))->toBe(['critical']);
});

test('notes, calls, e-mails and follow-ups do not change the business SLA', function () {
    $lead = bhSlaWaitingLead('2026-09-28 17:50');
    bhSlaNow('2026-09-29 08:30');
    $this->actingAs(User::factory()->create());

    foreach (['note', 'call', 'email'] as $type) {
        $this->post(route('leads.activities.store', $lead), [
            'type' => $type,
            'description' => "Registro {$type}.",
            'contacted_at' => '2026-09-29T08:30',
        ])->assertSessionHasNoErrors();
    }

    $this->patch(route('leads.follow-up', $lead), ['next_follow_up_at' => '2026-09-30 10:00'])
        ->assertSessionHasNoErrors();

    bhSlaNow('2026-09-29 08:49:59');
    expect($lead->refresh()->next_follow_up_at)->not->toBeNull()
        ->and($lead->activities()->where('type', '!=', 'whatsapp')->count())->toBe(4)
        ->and(bhSlaLevels($lead))->toBe(['urgent']);

    bhSlaNow('2026-09-29 08:50');
    expect(bhSlaLevels($lead))->toBe(['critical']);
});

test('converted and lost leads get the business SLA and keep their status', function (string $status) {
    $lead = bhSlaWaitingLead('2026-09-28 17:50', [
        'status' => $status,
        'lost_reason' => $status === 'lost' ? 'price' : null,
    ]);
    bhSlaNow('2026-09-29 08:50');

    expect(bhSlaLevels($lead))->toBe(['critical'])
        ->and($lead->refresh()->status)->toBe($status);
})->with(['converted', 'lost']);

test('filter, critical counter, dashboard and leads page use the same business-time rule', function () {
    // Às 08:10 de terça todos esperam mais de 60 min de relógio, mas não de expediente.
    $normal = bhSlaWaitingLead('2026-09-29 07:30', ['company_name' => 'Normal', 'whatsapp' => '5551900000001']);
    $attention = bhSlaWaitingLead('2026-09-28 17:55', ['company_name' => 'Atenção', 'whatsapp' => '5551900000002']);
    $urgent = bhSlaWaitingLead('2026-09-28 17:40', ['company_name' => 'Urgente', 'whatsapp' => '5551900000003']);
    $critical = bhSlaWaitingLead('2026-09-28 17:00', ['company_name' => 'Crítico', 'whatsapp' => '5551900000004']);
    bhSlaNow('2026-09-29 08:10');

    $this->actingAs(User::factory()->create());

    foreach (['normal' => $normal, 'attention' => $attention, 'urgent' => $urgent, 'critical' => $critical] as $level => $lead) {
        $this->get(route('leads.awaiting-reply', ['sla' => $level]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('leads.total', 1)
                ->where('leads.data.0.id', $lead->id));
    }

    expect(Lead::query()->whatsappReplySla('critical')->count())->toBe(1);

    $this->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('metrics.awaiting_reply', 4)
            ->where('metrics.awaiting_reply_critical', 1));

    $this->get(route('leads.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('awaitingReplyTotal', 4)
            ->where('awaitingReplyCritical', 1));
});

test('the business sla filter combines with owner, status, product and search', function () {
    $me = User::factory()->create();
    $other = User::factory()->create();
    // 17:00 de segunda até 08:10 de terça: 70 min úteis (crítico); 17:55: 15 min (atenção).
    $critical = '2026-09-28 17:00';

    $target = bhSlaWaitingLead($critical, ['company_name' => 'Padaria Alvo', 'user_id' => $me->id, 'status' => 'interested', 'product' => 'vetoros', 'whatsapp' => '5551900000001']);
    // Cada um difere do alvo em um único critério.
    bhSlaWaitingLead($critical, ['company_name' => 'Padaria Outro dono', 'user_id' => $other->id, 'status' => 'interested', 'product' => 'vetoros', 'whatsapp' => '5551900000002']);
    bhSlaWaitingLead($critical, ['company_name' => 'Padaria Outro status', 'user_id' => $me->id, 'status' => 'new', 'product' => 'vetoros', 'whatsapp' => '5551900000003']);
    bhSlaWaitingLead($critical, ['company_name' => 'Padaria Outro produto', 'user_id' => $me->id, 'status' => 'interested', 'product' => 'site', 'whatsapp' => '5551900000004']);
    bhSlaWaitingLead($critical, ['company_name' => 'Mercado Outra busca', 'user_id' => $me->id, 'status' => 'interested', 'product' => 'vetoros', 'whatsapp' => '5551900000005']);
    bhSlaWaitingLead('2026-09-28 17:55', ['company_name' => 'Padaria Outro SLA', 'user_id' => $me->id, 'status' => 'interested', 'product' => 'vetoros', 'whatsapp' => '5551900000006']);
    bhSlaNow('2026-09-29 08:10');

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

test('pagination counts only the leads in the business-time level', function () {
    $perPage = LeadController::AWAITING_REPLY_PER_PAGE;
    bhSlaNow('2026-09-29 08:10');

    // Esperam há mais de 14 h de relógio, mas só alguns minutos úteis: não são críticos.
    foreach (range(1, $perPage + 5) as $i) {
        bhSlaWaitingLead('2026-09-28 17:58', ['whatsapp' => '55519'.str_pad((string) $i, 8, '0', STR_PAD_LEFT)]);
    }

    $critical = collect(range(1, $perPage + 2))->map(fn (int $i) => bhSlaWaitingLead(
        CarbonImmutable::parse('2026-09-28 15:00')->addMinutes($i)->format('Y-m-d H:i'),
        ['whatsapp' => '55518'.str_pad((string) $i, 8, '0', STR_PAD_LEFT)],
    ));

    $this->actingAs(User::factory()->create());

    $first = $this->get(route('leads.awaiting-reply', ['sla' => 'critical']))->viewData('page')['props']['leads'];
    $second = $this->get(route('leads.awaiting-reply', ['sla' => 'critical', 'page' => 2]))->viewData('page')['props']['leads'];

    $ids = collect($first['data'])->merge($second['data'])->pluck('id');

    expect($first['total'])->toBe($perPage + 2)
        ->and($first['last_page'])->toBe(2)
        ->and($second['data'])->toHaveCount(2)
        ->and($ids->all())->toBe($critical->pluck('id')->all())
        ->and(Lead::query()->whatsappReplySla('critical')->count())->toBe($perPage + 2);
});

test('the queue sends the business wait and the clock the page needs to keep counting', function () {
    $lead = bhSlaWaitingLead('2026-09-28 17:55');
    bhSlaNow('2026-09-28 18:30');

    $this->actingAs(User::factory()->create())
        ->get(route('leads.awaiting-reply'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('leads.data.0.id', $lead->id)
            // UTC com Z: o navegador interpreta o instante sem depender do fuso local.
            ->where('leads.data.0.awaiting_reply_since', '2026-09-28T20:55:00.000000Z')
            ->where('leads.data.0.awaiting_reply_business_seconds', 300)
            ->where('slaClock.now', '2026-09-28T21:30:00.000000Z')
            ->where('slaClock.schedule', 'Seg a Sex, 08:00–18:00 (America/Sao_Paulo)')
            // Fechado agora: o próximo período é terça 08:00–18:00 de Brasília.
            ->where('slaClock.periods.0', ['2026-09-29T11:00:00.000000Z', '2026-09-29T21:00:00.000000Z'])
            ->has('slaClock.periods', 10)
            ->where('slaLevels', Lead::WHATSAPP_REPLY_SLA));
});

test('the next badge change happens after the weekend, not on friday at 18:10', function () {
    // Sexta 17:55: faltam 10 min úteis para Atenção; o próximo período começa segunda 08:00.
    $hours = app(WhatsappBusinessHours::class);
    $now = CarbonImmutable::parse('2026-10-02 18:00', 'America/Sao_Paulo');
    $periods = $hours->periodsBetween($now, $now->addDays(LeadController::AWAITING_REPLY_CLOCK_DAYS));

    expect($hours->elapsedSeconds(CarbonImmutable::parse('2026-10-02 17:55', 'America/Sao_Paulo'), $now))->toBe(300)
        ->and($periods[0][0]->toJSON())->toBe(bhSlaIso('2026-10-05 08:00'))
        // O mesmo corte do banco: Atenção chega segunda 08:10.
        ->and($hours->cutoff(CarbonImmutable::parse('2026-10-05 08:10', 'America/Sao_Paulo'), 15)?->toJSON())->toBe(bhSlaIso('2026-10-02 17:55'))
        ->and($hours->cutoff(CarbonImmutable::parse('2026-10-05 08:09:59', 'America/Sao_Paulo'), 15)?->toJSON())->toBe(bhSlaIso('2026-10-02 17:54:59'));
});

test('the operational timezone decides the business window, not the app timezone', function () {
    $lead = bhSlaWaitingLead('2026-09-28 08:30');
    Carbon::setTestNow('2026-09-28 12:15:00');

    expect(bhSlaUtc('2026-09-28 08:30'))->toBe('2026-09-28 11:30:00');

    // 12:15 UTC = 09:15 em Brasília: 45 min desde 08:30.
    expect(bhSlaLevels($lead))->toBe(['urgent']);

    // 12:15 UTC = 08:15 em Manaus (UTC-4): a mensagem das 07:30 de lá só conta desde 08:00.
    bhSlaWeek([], 'America/Manaus');

    expect(bhSlaLevels($lead))->toBe(['attention']);
});

test('without active days the SLA never leaves normal', function () {
    bhSlaWeek(array_fill(1, 7, ['active' => false, 'opens_at' => '08:00', 'closes_at' => '18:00']));
    $lead = bhSlaWaitingLead('2026-09-20 10:00');
    bhSlaNow('2026-09-28 10:00');

    expect(bhSlaLevels($lead))->toBe(['normal'])
        ->and(app(WhatsappBusinessHours::class)->cutoff(now(), 15))->toBeNull()
        ->and(Lead::query()->whatsappReplySla('critical')->count())->toBe(0)
        ->and(app(WhatsappBusinessHours::class)->description())->toBe('Nenhum dia de atendimento configurado');
});

test('invalid saved values fall back to the default of that field', function () {
    Setting::query()->delete();
    Setting::query()->create(['key' => Setting::WHATSAPP_SLA_TIMEZONE, 'value' => 'Mars/Olympus']);
    Setting::query()->create(['key' => Setting::WHATSAPP_SLA_WEEK, 'value' => json_encode([
        1 => ['active' => true, 'opens_at' => '09:00', 'closes_at' => '17:00'],
        2 => ['active' => true, 'opens_at' => '25:00', 'closes_at' => '17:00'],
        3 => ['active' => true, 'opens_at' => '18:00', 'closes_at' => '08:00'],
        4 => 'aberto',
        6 => ['active' => true, 'opens_at' => '08:00', 'closes_at' => '12:00'],
    ])]);

    $current = (new WhatsappBusinessHours)->current();

    expect($current['timezone'])->toBe('America/Sao_Paulo')
        ->and($current['days'][1])->toBe(['active' => true, 'opens_at' => '09:00', 'closes_at' => '17:00'])
        ->and($current['days'][2])->toBe(WhatsappBusinessHours::DEFAULTS['days'][2])
        ->and($current['days'][3])->toBe(WhatsappBusinessHours::DEFAULTS['days'][3])
        ->and($current['days'][4])->toBe(WhatsappBusinessHours::DEFAULTS['days'][4])
        ->and($current['days'][5])->toBe(WhatsappBusinessHours::DEFAULTS['days'][5])
        ->and($current['days'][6])->toBe(['active' => true, 'opens_at' => '08:00', 'closes_at' => '12:00'])
        ->and($current['days'][7])->toBe(WhatsappBusinessHours::DEFAULTS['days'][7]);
});

/** Horário de Brasília → ISO UTC, como a página recebe. */
function bhSlaIso(string $local): string
{
    return CarbonImmutable::parse($local, 'America/Sao_Paulo')->utc()->toJSON();
}
