<?php

use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function commercialLead(array $overrides = []): Lead
{
    return Lead::query()->create(array_merge([
        'company_name' => 'Assistência Comercial',
        'contact_name' => 'Maria',
        'city' => 'Canoas',
        'product' => 'vetoros',
        'status' => 'contacted',
        'whatsapp' => '51999996666',
    ], $overrides));
}

function statusNotes(Lead $lead): array
{
    return $lead->activities()->where('type', 'note')->pluck('description')->all();
}

test('the lead page gives the conversation header the existing commercial data', function () {
    $owner = User::factory()->create(['name' => 'Responsável Comercial']);
    $lead = commercialLead(['user_id' => $owner->id]);

    $this->actingAs(User::factory()->create())
        ->get(route('leads.edit', $lead))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('leads/edit')
            ->where('lead.contact_name', 'Maria')
            ->where('lead.company_name', 'Assistência Comercial')
            ->where('lead.city', 'Canoas')
            ->where('lead.whatsapp', '51999996666')
            ->where('lead.status', 'contacted')
            ->where('lead.user.name', 'Responsável Comercial')
            ->where('statuses', Lead::STATUSES)
            ->where('lostReasons', Lead::LOST_REASONS)
            ->where('whatsappDestination', '5551999996666'));
});

test('the conversation changes the lead stage through the existing status endpoint', function () {
    $user = User::factory()->create();
    $lead = commercialLead();

    $this->actingAs($user)
        ->from(route('leads.edit', $lead))
        ->patch(route('leads.status', $lead), ['status' => 'interested', 'origin' => 'whatsapp'])
        ->assertRedirect(route('leads.edit', $lead))
        ->assertSessionHasNoErrors();

    expect($lead->refresh()->status)->toBe('interested')
        ->and(statusNotes($lead))->toBe(['Status alterado de Contatado para Interessado pela conversa WhatsApp.']);

    $note = $lead->activities()->where('type', 'note')->first();
    expect($note->user_id)->toBe($user->id)
        ->and($note->status)->toBe('interested')
        ->and($note->direction)->toBeNull();
});

test('the kanban text stays the default when no origin is sent', function () {
    $lead = commercialLead();

    $this->actingAs(User::factory()->create())
        ->patch(route('leads.status', $lead), ['status' => 'meeting'])
        ->assertSessionHasNoErrors();

    $this->actingAs(User::factory()->create())
        ->patch(route('leads.status', $lead), ['status' => 'converted', 'origin' => 'kanban'])
        ->assertSessionHasNoErrors();

    expect(statusNotes($lead))->toEqualCanonicalizing([
        'Status alterado de Contatado para Reunião pelo Kanban.',
        'Status alterado de Reunião para Convertido pelo Kanban.',
    ]);
});

test('marking the lead as lost from the conversation still requires the lost reason', function () {
    $lead = commercialLead();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('leads.status', $lead), ['status' => 'lost', 'origin' => 'whatsapp'])
        ->assertSessionHasErrors('lost_reason');

    expect($lead->refresh()->status)->toBe('contacted');

    $this->actingAs($user)
        ->patch(route('leads.status', $lead), ['status' => 'lost', 'lost_reason' => 'no_response', 'origin' => 'whatsapp'])
        ->assertSessionHasNoErrors();

    expect($lead->refresh())
        ->status->toBe('lost')
        ->lost_reason->toBe('no_response')
        ->and(statusNotes($lead))->toBe(['Status alterado de Contatado para Perdido pela conversa WhatsApp (motivo: Sem resposta).']);
});

test('an unknown origin or status is rejected without touching the lead', function (array $payload, string $field) {
    $lead = commercialLead();

    $this->actingAs(User::factory()->create())
        ->patch(route('leads.status', $lead), $payload)
        ->assertSessionHasErrors($field);

    expect($lead->refresh()->status)->toBe('contacted')
        ->and(statusNotes($lead))->toBe([]);
})->with([
    'unknown origin' => [['status' => 'meeting', 'origin' => 'api'], 'origin'],
    'origin not a string' => [['status' => 'meeting', 'origin' => ['whatsapp']], 'origin'],
    'unknown status' => [['status' => 'negotiation', 'origin' => 'whatsapp'], 'status'],
]);

test('users without access to leads cannot change the stage from the conversation', function () {
    $lead = commercialLead();

    $this->patch(route('leads.status', $lead), ['status' => 'meeting', 'origin' => 'whatsapp'])
        ->assertRedirect(route('login'));

    $this->actingAs(User::factory()->reader()->create())
        ->patch(route('leads.status', $lead), ['status' => 'meeting', 'origin' => 'whatsapp'])
        ->assertForbidden();

    expect($lead->refresh()->status)->toBe('contacted');
});

test('the stage change note does not enter the WhatsApp conversation nor its unread count', function () {
    $lead = commercialLead();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('leads.status', $lead), ['status' => 'meeting', 'origin' => 'whatsapp'])
        ->assertSessionHasNoErrors();

    $this->actingAs($user)
        ->getJson(route('leads.whatsapp.messages', ['lead' => $lead, 'since' => 0]))
        ->assertOk()
        ->assertJsonPath('messages', []);

    expect($lead->unreadWhatsappMessages()->count())->toBe(0)
        ->and(LeadActivity::query()->where('lead_id', $lead->id)->where('type', 'whatsapp')->exists())->toBeFalse();
});
