<?php

use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function leadForHistory(User $user): Lead
{
    return Lead::query()->create([
        'user_id' => $user->id,
        'company_name' => 'Lead com histórico WhatsApp',
        'product' => 'site',
        'status' => 'contacted',
    ]);
}

test('lead history exposes the WhatsApp message status', function (string $messageStatus) {
    $user = User::factory()->create();
    $lead = leadForHistory($user);

    $lead->activities()->create([
        'type' => 'whatsapp',
        'contacted_at' => now(),
        'description' => 'Olá, tudo bem? Somos da ABrasil Sistemas.',
        'provider_message_id' => 'true_5551999999999@c.us_3EB0F27EBBA9D67C6210DD',
        'message_status' => $messageStatus,
    ]);

    $this->actingAs($user)->get(route('leads.edit', $lead))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('leads/edit')
            ->has('lead.activities', 1)
            ->where('lead.activities.0.type', 'whatsapp')
            ->where('lead.activities.0.message_status', $messageStatus)
            ->missing('lead.activities.0.provider_message_id'));
})->with(LeadActivity::MESSAGE_STATUSES);

test('lead history keeps legacy WhatsApp activities without message status', function () {
    $user = User::factory()->create();
    $lead = leadForHistory($user);

    $lead->activities()->create([
        'type' => 'whatsapp',
        'contacted_at' => now(),
        'description' => 'Mensagem antiga, anterior aos ACKs.',
    ]);

    $this->actingAs($user)->get(route('leads.edit', $lead))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('lead.activities', 1)
            ->where('lead.activities.0.type', 'whatsapp')
            ->where('lead.activities.0.message_status', null));
});

test('lead history does not carry a technical status for non WhatsApp activities', function () {
    $user = User::factory()->create();
    $lead = leadForHistory($user);

    $lead->activities()->create([
        'user_id' => $user->id,
        'type' => 'note',
        'status' => 'contacted',
        'contacted_at' => now(),
        'description' => 'Ligação registrada manualmente.',
    ]);

    $this->actingAs($user)->get(route('leads.edit', $lead))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('lead.activities.0.type', 'note')
            ->where('lead.activities.0.status', 'contacted')
            ->where('lead.activities.0.message_status', null));
});

test('lead history loads mixed activities with their message status', function () {
    $user = User::factory()->create();
    $lead = leadForHistory($user);

    $older = $lead->activities()->create([
        'type' => 'note',
        'description' => 'Nota inicial.',
    ]);
    $older->forceFill(['created_at' => now()->subHour()])->save();

    $lead->activities()->create([
        'type' => 'whatsapp',
        'contacted_at' => now(),
        'description' => 'Mensagem lida.',
        'provider_message_id' => '3EB0READ',
        'message_status' => LeadActivity::MESSAGE_STATUS_READ,
    ]);

    $this->actingAs($user)->get(route('leads.edit', $lead))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('lead.activities', 2)
            ->where('lead.activities.0.type', 'whatsapp')
            ->where('lead.activities.0.message_status', 'READ')
            ->where('lead.activities.1.type', 'note')
            ->where('lead.activities.1.message_status', null));
});
