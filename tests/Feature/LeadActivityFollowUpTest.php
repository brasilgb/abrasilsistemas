<?php

use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\User;

/*
 * CRM-WA-09: registrar contato / atividade rápida só mexe no próximo contato quando o operador
 * pede (follow_up_action = set | clear). Campo vazio ou ausente mantém leads.next_follow_up_at.
 */

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 9, 27)->setTime(12, 0));
});

function activityFollowUpLead(User $user, ?string $nextFollowUpAt = '2026-09-29 10:00'): Lead
{
    return Lead::query()->create([
        'user_id' => $user->id,
        'company_name' => 'Lead do próximo contato',
        'product' => 'site',
        'status' => 'contacted',
        'next_follow_up_at' => $nextFollowUpAt,
    ]);
}

function activityFollowUpNotes(Lead $lead)
{
    return LeadActivity::query()
        ->where('lead_id', $lead->id)
        ->where('description', 'like', 'Próximo contato%')
        ->pluck('description');
}

test('registering an activity without a follow up action keeps the current follow up', function () {
    $user = User::factory()->create();
    $lead = activityFollowUpLead($user);
    $before = $lead->fresh()->next_follow_up_at;
    $notesBefore = activityFollowUpNotes($lead)->count();

    // Payload do formulário "Registrar contato" com "Manter" e o campo de data vazio.
    $this->actingAs($user)->post(route('leads.activities.store', $lead), [
        'type' => 'call',
        'status' => '',
        'contacted_at' => '',
        'follow_up_action' => 'keep',
        'next_follow_up_at' => '',
        'description' => 'Ligação realizada',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $lead->refresh();

    expect($lead->next_follow_up_at->equalTo($before))->toBeTrue()
        ->and(Lead::formatFollowUpAt($lead->next_follow_up_at))->toBe('29/09/2026 às 10:00')
        ->and(activityFollowUpNotes($lead)->count())->toBe($notesBefore)
        ->and($lead->activities()->where('description', 'Ligação realizada')->sole()->next_follow_up_at)->toBeNull();
});

test('an empty or missing follow up field without an action never clears the follow up', function (array $payload) {
    $user = User::factory()->create();
    $lead = activityFollowUpLead($user);
    $before = $lead->fresh()->next_follow_up_at;

    $this->actingAs($user)->post(route('leads.activities.store', $lead), [
        'type' => 'call',
        'description' => 'Ligação realizada',
        ...$payload,
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect($lead->fresh()->next_follow_up_at->equalTo($before))->toBeTrue()
        ->and(activityFollowUpNotes($lead))->toBeEmpty();
})->with([
    'campo vazio (formulário antigo)' => [['next_follow_up_at' => '']],
    'campo nulo' => [['next_follow_up_at' => null]],
    'campo ausente' => [[]],
]);

test('registering an activity with a new follow up changes it and records the change', function () {
    $user = User::factory()->create();
    $lead = activityFollowUpLead($user);

    $this->actingAs($user)->post(route('leads.activities.store', $lead), [
        'type' => 'whatsapp',
        'follow_up_action' => 'set',
        'next_follow_up_at' => '2026-10-02T15:30',
        'description' => 'Pediu retorno na sexta.',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $lead->refresh();

    expect(Lead::formatFollowUpAt($lead->next_follow_up_at))->toBe('02/10/2026 às 15:30')
        ->and(activityFollowUpNotes($lead)->last())
        ->toBe('Próximo contato alterado de 29/09/2026 às 10:00 para 02/10/2026 às 15:30.');
});

test('a filled date without an action still changes the follow up', function () {
    $user = User::factory()->create();
    $lead = activityFollowUpLead($user);

    $this->actingAs($user)->post(route('leads.activities.store', $lead), [
        'type' => 'call',
        'next_follow_up_at' => '2026-10-05T09:00',
        'description' => 'Retornar na segunda.',
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect(Lead::formatFollowUpAt($lead->fresh()->next_follow_up_at))->toBe('05/10/2026 às 09:00');
});

test('asking to change the follow up requires a date and changes nothing without it', function () {
    $user = User::factory()->create();
    $lead = activityFollowUpLead($user);
    $before = $lead->fresh()->next_follow_up_at;

    $this->actingAs($user)->post(route('leads.activities.store', $lead), [
        'type' => 'call',
        'follow_up_action' => 'set',
        'next_follow_up_at' => '',
        'description' => 'Ligação realizada',
    ])->assertSessionHasErrors('next_follow_up_at');

    expect($lead->fresh()->next_follow_up_at->equalTo($before))->toBeTrue()
        ->and($lead->activities()->where('description', 'Ligação realizada')->exists())->toBeFalse();
});

test('an unknown follow up action is rejected', function () {
    $user = User::factory()->create();
    $lead = activityFollowUpLead($user);

    $this->actingAs($user)->post(route('leads.activities.store', $lead), [
        'type' => 'call',
        'follow_up_action' => 'remove-all',
        'description' => 'Ligação realizada',
    ])->assertSessionHasErrors('follow_up_action');

    expect($lead->fresh()->next_follow_up_at)->not->toBeNull();
});

test('removing the follow up requires the explicit clear action and is recorded', function () {
    $user = User::factory()->create();
    $lead = activityFollowUpLead($user);

    $this->actingAs($user)->post(route('leads.activities.store', $lead), [
        'type' => 'note',
        'follow_up_action' => 'clear',
        'next_follow_up_at' => '',
        'description' => 'Cliente pediu para não ligar mais esta semana.',
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect($lead->fresh()->next_follow_up_at)->toBeNull()
        ->and(activityFollowUpNotes($lead)->last())
        ->toBe('Próximo contato removido (estava em 29/09/2026 às 10:00).');
});

test('a lead without follow up stays without one and gets no artificial history', function (?string $action) {
    $user = User::factory()->create();
    $lead = activityFollowUpLead($user, null);

    $this->actingAs($user)->post(route('leads.activities.store', $lead), array_filter([
        'type' => 'call',
        'follow_up_action' => $action,
        'next_follow_up_at' => '',
        'description' => 'Ligação realizada',
    ], fn ($value) => $value !== null))->assertRedirect()->assertSessionHasNoErrors();

    expect($lead->fresh()->next_follow_up_at)->toBeNull()
        ->and(activityFollowUpNotes($lead))->toBeEmpty()
        ->and($lead->activities()->count())->toBe(1);
})->with([
    'manter' => ['keep'],
    'sem ação' => [null],
    'remover sem follow-up' => ['clear'],
]);

test('kanban quick activity keeps the follow up when it is not changed', function () {
    $user = User::factory()->create();
    $lead = activityFollowUpLead($user);
    $before = $lead->fresh()->next_follow_up_at;

    // Payload da "Atividade rápida" do Kanban com "Manter" selecionado.
    $this->actingAs($user)->post(route('leads.activities.store', $lead), [
        'type' => 'whatsapp',
        'status' => 'interested',
        'lost_reason' => '',
        'follow_up_action' => 'keep',
        'description' => 'Respondeu com interesse.',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $lead->refresh();

    expect($lead->status)->toBe('interested')
        ->and($lead->next_follow_up_at->equalTo($before))->toBeTrue()
        ->and(activityFollowUpNotes($lead))->toBeEmpty();
});
