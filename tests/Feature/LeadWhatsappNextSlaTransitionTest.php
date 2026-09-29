<?php

use App\Http\Controllers\LeadController;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\Setting;
use App\Models\User;
use App\Services\WhatsappBusinessHours;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

/*
 * Próxima mudança de nível do SLA em horário de atendimento (CRM-WA-15). Mesma jornada dos testes
 * do CRM-WA-14: segunda a sexta 08:00–18:00, sábado e domingo fechados, America/Sao_Paulo (UTC-3).
 * Horários locais (Brasília). 2026-09-28 é segunda-feira; 2026-10-02 sexta; 2026-10-03 sábado.
 */

function nextSlaUtc(string $local): string
{
    return CarbonImmutable::parse($local, 'America/Sao_Paulo')->utc()->toDateTimeString();
}

function nextSlaIso(string $local): string
{
    return CarbonImmutable::parse($local, 'America/Sao_Paulo')->utc()->toJSON();
}

function nextSlaNow(string $local): void
{
    Carbon::setTestNow(CarbonImmutable::parse($local, 'America/Sao_Paulo')->utc());
}

/** Jornada de teste; $days troca dias específicos (ex.: sábado aberto). */
function nextSlaWeek(array $days = [], string $timezone = 'America/Sao_Paulo'): void
{
    $weekday = ['active' => true, 'opens_at' => '08:00', 'closes_at' => '18:00'];
    $closed = ['active' => false, 'opens_at' => '08:00', 'closes_at' => '12:00'];

    app(WhatsappBusinessHours::class)->save([
        'timezone' => $timezone,
        'days' => array_replace([1 => $weekday, 2 => $weekday, 3 => $weekday, 4 => $weekday, 5 => $weekday, 6 => $closed, 7 => $closed], $days),
    ]);
}

function nextSlaMessage(Lead $lead, string $direction, string $local): void
{
    $at = nextSlaUtc($local);
    $activity = $lead->activities()->create([
        'type' => 'whatsapp',
        'direction' => $direction,
        'contacted_at' => $at,
        'description' => $direction === LeadActivity::DIRECTION_INBOUND ? 'Mensagem do cliente.' : 'Mensagem da empresa.',
        'provider_message_id' => 'msg_'.fake()->unique()->uuid(),
        'message_status' => $direction === LeadActivity::DIRECTION_OUTBOUND ? LeadActivity::MESSAGE_STATUS_SERVER : null,
    ]);

    $activity->forceFill(['created_at' => $at, 'updated_at' => $at])->save();
}

function nextSlaWaitingLead(string $sinceLocal, array $overrides = []): Lead
{
    $lead = Lead::query()->create(array_merge([
        'company_name' => 'Lead próxima transição',
        'product' => 'site',
        'status' => 'contacted',
        'whatsapp' => '5551995179173',
    ], $overrides));

    nextSlaMessage($lead, LeadActivity::DIRECTION_INBOUND, $sinceLocal);

    return $lead;
}

/**
 * Leads da fila como a página recebe, por id.
 *
 * @return array<int, array<string, mixed>>
 */
function nextSlaQueue(array $query = []): array
{
    return collect(test()->get(route('leads.awaiting-reply', $query))->viewData('page')['props']['leads']['data'])
        ->keyBy('id')
        ->all();
}

/** @return array{0: string|null, 1: string|null} [next_sla_level, next_sla_at] do lead na fila. */
function nextSlaOf(Lead $lead): array
{
    $row = nextSlaQueue()[$lead->id];

    return [$row['next_sla_level'], $row['next_sla_at']];
}

/** Nível do lead pelo filtro sla do banco (o mesmo do contador e do badge). */
function nextSlaLevelInDatabase(Lead $lead): ?string
{
    foreach (array_reverse(array_keys(Lead::WHATSAPP_REPLY_SLA)) as $level) {
        if (Lead::query()->whatsappReplySla($level)->whereKey($lead->id)->exists()) {
            return $level;
        }
    }

    return null;
}

/**
 * Confere a transição anunciada contra o filtro do banco: um segundo antes ainda é o nível atual
 * e, no instante anunciado, já é o próximo.
 */
function nextSlaAssertBoundary(Lead $lead, string $current, string $next, string $atLocal): void
{
    Carbon::setTestNow(CarbonImmutable::parse($atLocal, 'America/Sao_Paulo')->utc()->subSecond());
    expect(nextSlaLevelInDatabase($lead))->toBe($current);

    Carbon::setTestNow(CarbonImmutable::parse($atLocal, 'America/Sao_Paulo')->utc());
    expect(nextSlaLevelInDatabase($lead))->toBe($next);
}

beforeEach(function () {
    nextSlaWeek();
    $this->actingAs(User::factory()->create());
});

afterEach(function () {
    Carbon::setTestNow();
});

test('addBusinessSeconds walks only through business periods', function (string $start, int $minutes, string $expected) {
    $at = app(WhatsappBusinessHours::class)->addBusinessSeconds(CarbonImmutable::parse($start, 'America/Sao_Paulo'), $minutes * 60);

    expect($at?->toJSON())->toBe(nextSlaIso($expected))
        // Inverso exato de elapsedSeconds (mesma fonte de verdade do SLA).
        ->and(app(WhatsappBusinessHours::class)->elapsedSeconds(CarbonImmutable::parse($start, 'America/Sao_Paulo'), $at))
        ->toBe($minutes * 60);
})->with([
    'same business day' => ['2026-09-28 10:00', 15, '2026-09-28 10:15'],
    'crossing the closing' => ['2026-09-28 17:55', 15, '2026-09-29 08:10'],
    'friday to monday' => ['2026-10-02 17:55', 15, '2026-10-05 08:10'],
    'after the closing' => ['2026-09-28 22:00', 15, '2026-09-29 08:15'],
    'before the opening' => ['2026-09-28 07:30', 15, '2026-09-28 08:15'],
    'exactly at the opening' => ['2026-09-28 08:00', 15, '2026-09-28 08:15'],
    'exactly at the closing' => ['2026-09-28 18:00', 15, '2026-09-29 08:15'],
    'ending exactly at the closing' => ['2026-09-28 17:45', 15, '2026-09-28 18:00'],
    'sunday is closed' => ['2026-10-04 10:00', 15, '2026-10-05 08:15'],
    'several business days' => ['2026-10-01 17:00', 60 * 11 + 1, '2026-10-05 08:01'],
]);

test('addBusinessSeconds ignores fractions, returns the start for zero and null without active days', function () {
    $hours = app(WhatsappBusinessHours::class);

    expect($hours->addBusinessSeconds(CarbonImmutable::parse('2026-09-28 10:00:00.999999', 'America/Sao_Paulo'), 900)?->toJSON())
        ->toBe(nextSlaIso('2026-09-28 10:15'))
        ->and($hours->addBusinessSeconds(CarbonImmutable::parse('2026-09-28 22:00', 'America/Sao_Paulo'), 0)?->toJSON())
        ->toBe(nextSlaIso('2026-09-28 22:00'));

    nextSlaWeek(array_fill(1, 7, ['active' => false, 'opens_at' => '08:00', 'closes_at' => '18:00']));

    expect(app(WhatsappBusinessHours::class)->addBusinessSeconds(now(), 900))->toBeNull();
});

test('the next level comes from the existing SLA thresholds', function () {
    expect(Lead::nextWhatsappReplySla(0))->toBe('attention')
        ->and(Lead::nextWhatsappReplySla(Lead::WHATSAPP_REPLY_SLA['attention']['from_minutes'] * 60 - 1))->toBe('attention')
        ->and(Lead::nextWhatsappReplySla(Lead::WHATSAPP_REPLY_SLA['attention']['from_minutes'] * 60))->toBe('urgent')
        ->and(Lead::nextWhatsappReplySla(Lead::WHATSAPP_REPLY_SLA['urgent']['from_minutes'] * 60))->toBe('critical')
        ->and(Lead::nextWhatsappReplySla(Lead::WHATSAPP_REPLY_SLA['critical']['from_minutes'] * 60 - 1))->toBe('critical')
        ->and(Lead::nextWhatsappReplySla(Lead::WHATSAPP_REPLY_SLA['critical']['from_minutes'] * 60))->toBeNull();
});

test('normal to attention inside the same business day', function () {
    $lead = nextSlaWaitingLead('2026-09-28 14:20');
    nextSlaNow('2026-09-28 14:25');

    expect(nextSlaOf($lead))->toBe(['attention', nextSlaIso('2026-09-28 14:35')]);
    nextSlaAssertBoundary($lead, 'normal', 'attention', '2026-09-28 14:35');
});

test('attention to urgent', function () {
    $lead = nextSlaWaitingLead('2026-09-28 14:20');
    nextSlaNow('2026-09-28 14:42');

    expect(nextSlaOf($lead))->toBe(['urgent', nextSlaIso('2026-09-28 14:50')]);
    nextSlaAssertBoundary($lead, 'attention', 'urgent', '2026-09-28 14:50');
});

test('urgent to critical', function () {
    $lead = nextSlaWaitingLead('2026-09-28 14:20');
    nextSlaNow('2026-09-28 15:00');

    expect(nextSlaOf($lead))->toBe(['critical', nextSlaIso('2026-09-28 15:20')]);
    nextSlaAssertBoundary($lead, 'urgent', 'critical', '2026-09-28 15:20');
});

test('critical has no next transition', function () {
    $lead = nextSlaWaitingLead('2026-09-28 14:20');
    nextSlaNow('2026-09-28 15:20');

    expect(nextSlaOf($lead))->toBe([null, null]);

    nextSlaNow('2026-10-01 10:00');
    expect(nextSlaOf($lead))->toBe([null, null]);
});

test('a transition crossing the closing lands on the next opening', function () {
    $lead = nextSlaWaitingLead('2026-09-28 17:55');

    foreach (['2026-09-28 17:58', '2026-09-28 18:00', '2026-09-28 23:30', '2026-09-29 08:05'] as $now) {
        nextSlaNow($now);
        expect(nextSlaOf($lead))->toBe(['attention', nextSlaIso('2026-09-29 08:10')]);
    }

    nextSlaAssertBoundary($lead, 'normal', 'attention', '2026-09-29 08:10');
});

test('friday to monday when the weekend is closed', function () {
    $lead = nextSlaWaitingLead('2026-10-02 17:55');

    foreach (['2026-10-02 18:00', '2026-10-03 10:00', '2026-10-04 23:59'] as $now) {
        nextSlaNow($now);
        expect(nextSlaOf($lead))->toBe(['attention', nextSlaIso('2026-10-05 08:10')]);
    }

    nextSlaAssertBoundary($lead, 'normal', 'attention', '2026-10-05 08:10');
});

test('an open saturday takes part in the transition', function () {
    nextSlaWeek([6 => ['active' => true, 'opens_at' => '08:00', 'closes_at' => '12:00']]);
    $lead = nextSlaWaitingLead('2026-10-02 17:55');
    nextSlaNow('2026-10-02 19:00');

    expect(nextSlaOf($lead))->toBe(['attention', nextSlaIso('2026-10-03 08:10')]);
    nextSlaAssertBoundary($lead, 'normal', 'attention', '2026-10-03 08:10');

    // Sábado fecha às 12:00: Urgente (30 min) só na segunda, com os 15 min que faltam.
    nextSlaWaitingLead('2026-10-03 11:45', ['whatsapp' => '5551900000002']);
    nextSlaNow('2026-10-03 11:50');
    $saturday = Lead::query()->where('whatsapp', '5551900000002')->first();

    expect(nextSlaOf($saturday))->toBe(['attention', nextSlaIso('2026-10-03 12:00')]);

    nextSlaNow('2026-10-03 12:00');
    expect(nextSlaOf($saturday))->toBe(['urgent', nextSlaIso('2026-10-05 08:15')]);
});

test('a closed saturday and sunday do not consume time', function () {
    $lead = nextSlaWaitingLead('2026-10-03 10:00');
    nextSlaNow('2026-10-04 15:00');

    expect(nextSlaOf($lead))->toBe(['attention', nextSlaIso('2026-10-05 08:15')]);
    nextSlaAssertBoundary($lead, 'normal', 'attention', '2026-10-05 08:15');
});

test('a message before the opening counts from the opening', function () {
    $lead = nextSlaWaitingLead('2026-09-28 07:30');
    nextSlaNow('2026-09-28 07:45');

    expect(nextSlaOf($lead))->toBe(['attention', nextSlaIso('2026-09-28 08:15')]);
    nextSlaAssertBoundary($lead, 'normal', 'attention', '2026-09-28 08:15');
});

test('a message after the closing counts from the next opening', function () {
    $lead = nextSlaWaitingLead('2026-09-28 22:00');
    nextSlaNow('2026-09-28 22:30');

    expect(nextSlaOf($lead))->toBe(['attention', nextSlaIso('2026-09-29 08:15')]);
    nextSlaAssertBoundary($lead, 'normal', 'attention', '2026-09-29 08:15');
});

test('a message exactly at the opening starts counting at once', function () {
    $lead = nextSlaWaitingLead('2026-09-28 08:00');
    nextSlaNow('2026-09-28 08:00');

    expect(nextSlaOf($lead))->toBe(['attention', nextSlaIso('2026-09-28 08:15')]);
    nextSlaAssertBoundary($lead, 'normal', 'attention', '2026-09-28 08:15');
});

test('a message exactly at the closing does not consume time after it', function () {
    $lead = nextSlaWaitingLead('2026-09-28 18:00');
    nextSlaNow('2026-09-28 18:00');

    expect(nextSlaOf($lead))->toBe(['attention', nextSlaIso('2026-09-29 08:15')]);
    nextSlaAssertBoundary($lead, 'normal', 'attention', '2026-09-29 08:15');
});

test('a threshold reached exactly at the closing changes at the closing', function () {
    $lead = nextSlaWaitingLead('2026-09-28 17:45');
    nextSlaNow('2026-09-28 17:50');

    expect(nextSlaOf($lead))->toBe(['attention', nextSlaIso('2026-09-28 18:00')]);
    nextSlaAssertBoundary($lead, 'normal', 'attention', '2026-09-28 18:00');

    // Às 18:00 já é Atenção; Urgente fica para a abertura seguinte + 15 min.
    nextSlaNow('2026-09-28 18:00');
    expect(nextSlaOf($lead))->toBe(['urgent', nextSlaIso('2026-09-29 08:15')]);
});

test('the operational timezone decides the transition, not the app timezone', function () {
    expect(config('app.timezone'))->toBe('UTC');

    // 11:30 UTC = 07:30 em Manaus (UTC-4): conta desde 08:00 de lá = 12:00 UTC.
    nextSlaWeek([], 'America/Manaus');
    $lead = nextSlaWaitingLead('2026-09-28 08:30');
    Carbon::setTestNow('2026-09-28 11:45:00');

    expect(nextSlaOf($lead))->toBe(['attention', '2026-09-28T12:15:00.000000Z']);

    // Mesma mensagem, jornada em Brasília: conta desde 11:30 UTC, já é Atenção e Urgente vem às 12:00 UTC.
    nextSlaWeek();
    expect(nextSlaOf($lead))->toBe(['urgent', '2026-09-28T12:00:00.000000Z']);
});

test('an outbound ends the wait and removes the next transition', function () {
    $lead = nextSlaWaitingLead('2026-09-28 14:20');
    nextSlaMessage($lead, LeadActivity::DIRECTION_OUTBOUND, '2026-09-28 14:30');
    nextSlaNow('2026-09-28 14:31');

    expect(nextSlaQueue())->not->toHaveKey($lead->id);
});

test('a new inbound restarts the calculation', function () {
    $lead = nextSlaWaitingLead('2026-09-28 14:20');
    nextSlaMessage($lead, LeadActivity::DIRECTION_OUTBOUND, '2026-09-28 14:30');
    nextSlaMessage($lead, LeadActivity::DIRECTION_INBOUND, '2026-09-28 16:00');
    nextSlaNow('2026-09-28 16:05');

    $row = nextSlaQueue()[$lead->id];

    expect($row['awaiting_reply_since'])->toBe(nextSlaIso('2026-09-28 16:00'))
        ->and([$row['next_sla_level'], $row['next_sla_at']])->toBe(['attention', nextSlaIso('2026-09-28 16:15')]);

    // Outra inbound sem resposta não reinicia: a espera segue desde a primeira.
    nextSlaMessage($lead, LeadActivity::DIRECTION_INBOUND, '2026-09-28 16:10');
    nextSlaNow('2026-09-28 16:11');

    expect(nextSlaOf($lead))->toBe(['attention', nextSlaIso('2026-09-28 16:15')]);
});

test('at the exact threshold the level has already changed', function () {
    $lead = nextSlaWaitingLead('2026-09-28 14:20');

    nextSlaNow('2026-09-28 14:34:59');
    expect(nextSlaOf($lead))->toBe(['attention', nextSlaIso('2026-09-28 14:35')]);

    nextSlaNow('2026-09-28 14:35');
    expect(nextSlaOf($lead))->toBe(['urgent', nextSlaIso('2026-09-28 14:50')])
        ->and(nextSlaLevelInDatabase($lead))->toBe('attention');
});

test('fractions of a second on the clock do not move the boundary', function () {
    $lead = nextSlaWaitingLead('2026-09-28 14:20');

    Carbon::setTestNow(CarbonImmutable::parse('2026-09-28 14:34:59.999999', 'America/Sao_Paulo')->utc());
    $page = test()->get(route('leads.awaiting-reply'))->viewData('page')['props'];

    expect($page['leads']['data'][0]['next_sla_at'])->toBe(nextSlaIso('2026-09-28 14:35'))
        ->and($page['leads']['data'][0]['awaiting_reply_business_seconds'])->toBe(899)
        // O relógio da página vai no segundo cheio: espera + tempo desde ele = limite em next_sla_at.
        ->and($page['slaClock']['now'])->toBe(nextSlaIso('2026-09-28 14:34:59'));

    Carbon::setTestNow(CarbonImmutable::parse('2026-09-28 14:35:00.000001', 'America/Sao_Paulo')->utc());
    expect(nextSlaOf($lead))->toBe(['urgent', nextSlaIso('2026-09-28 14:50')]);
});

test('the business hours saved in the database drive the transition', function () {
    Setting::query()->delete();
    Setting::query()->create(['key' => Setting::WHATSAPP_SLA_TIMEZONE, 'value' => 'America/Sao_Paulo']);
    Setting::query()->create(['key' => Setting::WHATSAPP_SLA_WEEK, 'value' => json_encode(array_fill(1, 7, [
        'active' => true, 'opens_at' => '13:00', 'closes_at' => '14:00',
    ]))]);
    app()->forgetScopedInstances();

    $lead = nextSlaWaitingLead('2026-09-28 13:50');
    nextSlaNow('2026-09-28 13:55');

    // 10 min úteis até 14:00 e os 5 restantes na abertura seguinte (13:00 de terça).
    expect(nextSlaOf($lead))->toBe(['attention', nextSlaIso('2026-09-29 13:05')]);
    nextSlaAssertBoundary($lead, 'normal', 'attention', '2026-09-29 13:05');
});

test('without active days there is no next transition', function () {
    nextSlaWeek(array_fill(1, 7, ['active' => false, 'opens_at' => '08:00', 'closes_at' => '18:00']));
    $lead = nextSlaWaitingLead('2026-09-20 10:00');
    nextSlaNow('2026-09-28 10:00');

    expect(nextSlaOf($lead))->toBe([null, null]);
});

test('pagination and the sla filter return the same leads, each with its own transition', function () {
    $perPage = LeadController::AWAITING_REPLY_PER_PAGE;
    nextSlaNow('2026-09-28 16:00');

    $leads = collect(range(1, $perPage + 3))->map(fn (int $i) => nextSlaWaitingLead(
        CarbonImmutable::parse('2026-09-28 15:00')->addMinutes($i * 2)->format('Y-m-d H:i'),
        ['whatsapp' => '55517'.str_pad((string) $i, 8, '0', STR_PAD_LEFT)],
    ));

    $first = $this->get(route('leads.awaiting-reply'))->viewData('page')['props']['leads'];
    $second = $this->get(route('leads.awaiting-reply', ['page' => 2]))->viewData('page')['props']['leads'];
    $rows = collect($first['data'])->merge($second['data']);

    expect($first['total'])->toBe($perPage + 3)
        ->and($rows->pluck('id')->all())->toBe($leads->pluck('id')->all());

    // Cada lead: 15:00 + 2i min, esperando de 58 a 4 min às 16:00 (Crítico ainda por vir).
    $rows->each(function (array $row, int $index) {
        $since = CarbonImmutable::parse('2026-09-28 15:00', 'America/Sao_Paulo')->addMinutes(($index + 1) * 2);
        $minutes = $since->diffInMinutes(CarbonImmutable::parse('2026-09-28 16:00', 'America/Sao_Paulo'));
        $level = Lead::nextWhatsappReplySla((int) $minutes * 60);

        expect($row['next_sla_level'])->toBe($level)
            ->and($row['next_sla_at'])->toBe($level === null ? null : $since->addMinutes(Lead::WHATSAPP_REPLY_SLA[$level]['from_minutes'])->utc()->toJSON());
    });

    $urgent = collect($this->get(route('leads.awaiting-reply', ['sla' => 'urgent']))->viewData('page')['props']['leads']['data']);

    expect($urgent)->not->toBeEmpty()
        ->and($urgent->pluck('next_sla_level')->unique()->all())->toBe(['critical']);
});

test('the queue payload keeps the existing fields and adds only the next transition', function () {
    nextSlaWaitingLead('2026-09-28 14:20');
    nextSlaNow('2026-09-28 14:25');

    $row = nextSlaQueue();
    $row = reset($row);

    expect($row)->toHaveKeys(['awaiting_reply_since', 'awaiting_reply_business_seconds', 'last_inbound_at', 'last_inbound_message', 'next_sla_level', 'next_sla_at'])
        ->and($row['awaiting_reply_business_seconds'])->toBe(300);
});
