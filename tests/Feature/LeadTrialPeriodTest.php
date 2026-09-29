<?php

use App\Models\Lead;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * CRM-WA-16: período de teste do lead. trial_state é derivado das datas (none, active, expired),
 * com o dia de America/Sao_Paulo; Convertido/Perdido é sempre none. Sem novo status.
 * Desde o CRM-WA-23 as datas têm horário; só a data (como aqui) segue valendo como dia de
 * calendário. A edição manual pelo formulário saiu: ver LeadTrialStartTest.
 */

beforeEach(function () {
    // 28/09/2026 12:00 em São Paulo (15:00 UTC).
    $this->travelTo(now()->setDate(2026, 9, 28)->setTime(15, 0));
});

/** @param  array<string, mixed>  $attributes */
function trialPeriodLead(array $attributes = []): Lead
{
    return Lead::query()->create([
        'company_name' => 'Lead em teste '.uniqid(),
        'product' => 'vetoros',
        'status' => 'interested',
        ...$attributes,
    ]);
}

test('trial state is none without a trial start', function () {
    expect(trialPeriodLead()->trial_state)->toBe('none')
        ->and(Lead::query()->trialState('none')->count())->toBe(1);
});

test('trial state is active once started, with or without an end date not yet passed', function (?string $endsAt) {
    $lead = trialPeriodLead(['trial_started_at' => '2026-09-20', 'trial_ends_at' => $endsAt]);

    expect($lead->fresh()->trial_state)->toBe('active')
        ->and(Lead::query()->trialState('active')->pluck('id')->all())->toBe([$lead->id]);
})->with([
    'sem término' => [null],
    'termina hoje' => ['2026-09-28'],
    'termina depois' => ['2026-10-05'],
]);

test('trial state is expired when the end date is before today', function () {
    $lead = trialPeriodLead(['trial_started_at' => '2026-09-10', 'trial_ends_at' => '2026-09-27']);

    expect($lead->fresh()->trial_state)->toBe('expired')
        ->and(Lead::query()->trialState('expired')->pluck('id')->all())->toBe([$lead->id]);
});

test('the trial day boundary follows America/Sao_Paulo, not UTC', function () {
    $lead = trialPeriodLead(['trial_started_at' => '2026-09-20', 'trial_ends_at' => '2026-09-28']);

    // 29/09 02:00 UTC = 28/09 23:00 em São Paulo: o último dia do teste ainda não acabou.
    $this->travelTo(now('UTC')->setDate(2026, 9, 29)->setTime(2, 0));
    expect($lead->fresh()->trial_state)->toBe('active')
        ->and(Lead::query()->trialState('active')->count())->toBe(1)
        ->and(Lead::query()->trialState('expired')->count())->toBe(0);

    // 29/09 03:00 UTC = 29/09 00:00 em São Paulo: vencido.
    $this->travelTo(now('UTC')->setDate(2026, 9, 29)->setTime(3, 0));
    expect($lead->fresh()->trial_state)->toBe('expired')
        ->and(Lead::query()->trialState('active')->count())->toBe(0)
        ->and(Lead::query()->trialState('expired')->count())->toBe(1);
});

test('converted or lost leads never show an active or expired trial', function (string $status, ?string $endsAt) {
    $lead = trialPeriodLead([
        'status' => $status,
        'lost_reason' => $status === 'lost' ? 'price' : null,
        'trial_started_at' => '2026-09-10',
        'trial_ends_at' => $endsAt,
    ]);

    expect($lead->fresh()->trial_state)->toBe('none')
        ->and(Lead::query()->trialState('none')->pluck('id')->all())->toBe([$lead->id])
        ->and(Lead::query()->trialState('active')->count())->toBe(0)
        ->and(Lead::query()->trialState('expired')->count())->toBe(0);
})->with([
    'convertido em teste' => ['converted', '2026-10-10'],
    'convertido com teste vencido' => ['converted', '2026-09-20'],
    'perdido em teste' => ['lost', null],
    'perdido com teste vencido' => ['lost', '2026-09-20'],
]);

test('trial_state and the trialState scope always agree', function () {
    $combinations = [
        ['new', null, null],
        ['contacted', '2026-09-28', null],
        ['interested', '2026-09-20', '2026-09-28'],
        ['meeting', '2026-09-20', '2026-09-29'],
        ['meeting', '2026-09-01', '2026-09-27'],
        ['converted', '2026-09-01', '2026-09-27'],
        ['converted', '2026-09-20', null],
        ['lost', '2026-09-20', '2026-10-01'],
        ['lost', null, null],
    ];

    foreach ($combinations as [$status, $startedAt, $endsAt]) {
        trialPeriodLead([
            'status' => $status,
            'lost_reason' => $status === 'lost' ? 'other' : null,
            'trial_started_at' => $startedAt,
            'trial_ends_at' => $endsAt,
        ]);
    }

    $leads = Lead::query()->get();
    $counted = 0;

    foreach (array_keys(Lead::TRIAL_STATES) as $state) {
        $fromScope = Lead::query()->trialState($state)->orderBy('id')->pluck('id')->all();
        $fromAttribute = $leads->where('trial_state', $state)->sortBy('id')->pluck('id')->values()->all();

        expect($fromScope)->toBe($fromAttribute);
        $counted += count($fromScope);
    }

    // Cada lead cai em exatamente uma situação; situação desconhecida não filtra.
    expect($counted)->toBe(count($combinations))
        ->and(Lead::query()->trialState('unknown')->count())->toBe(count($combinations))
        ->and($leads->countBy('trial_state')->all())->toEqual(['none' => 5, 'active' => 3, 'expired' => 1]);
});

test('the leads page receives the trial state for the kanban and the list', function () {
    $user = User::factory()->create();
    trialPeriodLead(['user_id' => $user->id, 'trial_started_at' => '2026-09-20', 'trial_ends_at' => '2026-10-01']);

    $this->actingAs($user)
        ->get(route('leads.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('kanbanLeads.0.trial_state', 'active')
            // Vencimento às 00:00 de São Paulo do dia seguinte ao último dia (01/10).
            ->where('kanbanLeads.0.trial_ends_at', '2026-10-02T03:00:00.000000Z')
            ->where('leads.data.0.trial_state', 'active')
            ->where('leads.data.0.trial_started_at', '2026-09-20T03:00:00.000000Z'));
});
