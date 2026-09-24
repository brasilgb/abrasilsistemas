<?php

use App\Models\Lead;
use App\Models\LeadActivity;

function whatsappLogToken(): array
{
    return ['Authorization' => 'Bearer '.config('services.ab_prospect.token')];
}

function whatsappLogPayload(array $overrides = []): array
{
    return array_merge([
        'message_id' => '3EB0F27EBBA9D67C6210DD',
        'status' => 'PENDING',
        'message' => 'Teste completo CRM → n8n → WAHA.',
    ], $overrides);
}

test('the WhatsApp log API rejects requests without a valid token', function () {
    $lead = Lead::query()->create(['company_name' => 'Lead sem token']);

    $this->postJson("/api/prospects/{$lead->id}/whatsapp/log", whatsappLogPayload())
        ->assertUnauthorized();
});

test('the WhatsApp log API checks the token before resolving the lead', function () {
    $this->postJson('/api/prospects/999999/whatsapp/log', whatsappLogPayload())
        ->assertUnauthorized();
});

test('the WhatsApp log API rejects an invalid payload', function () {
    $lead = Lead::query()->create(['company_name' => 'Lead com payload inválido']);

    $this->postJson("/api/prospects/{$lead->id}/whatsapp/log", [
        'status' => 'PENDING',
    ], whatsappLogToken())->assertUnprocessable();
});

test('the WhatsApp log API returns not found for an unknown lead', function () {
    $this->postJson('/api/prospects/999999/whatsapp/log', whatsappLogPayload(), whatsappLogToken())
        ->assertNotFound();
});

test('the WhatsApp log API records and deduplicates a WhatsApp activity', function () {
    $lead = Lead::query()->create([
        'company_name' => 'Lead WhatsApp',
        'status' => 'interested',
    ]);

    $response = $this->postJson("/api/prospects/{$lead->id}/whatsapp/log", whatsappLogPayload(), whatsappLogToken())
        ->assertCreated()
        ->assertJson([
            'success' => true,
            'message' => 'Envio de WhatsApp registrado.',
        ]);

    $activityId = $response->json('activity_id');
    $activity = LeadActivity::query()->findOrFail($activityId);

    expect($activity->lead_id)->toBe($lead->id)
        ->and($activity->type)->toBe('whatsapp')
        ->and($activity->user_id)->toBeNull()
        ->and($activity->status)->toBeNull()
        ->and($activity->provider_message_id)->toBe('3EB0F27EBBA9D67C6210DD')
        ->and($activity->message_status)->toBe('PENDING')
        ->and($activity->description)->toBe('Teste completo CRM → n8n → WAHA.')
        ->and($activity->contacted_at)->not->toBeNull();

    expect($lead->fresh()->status)->toBe('interested')
        ->and($lead->fresh()->last_contacted_at)->not->toBeNull();

    $this->postJson("/api/prospects/{$lead->id}/whatsapp/log", whatsappLogPayload(), whatsappLogToken())
        ->assertOk()
        ->assertJson([
            'success' => true,
            'idempotent' => true,
            'activity_id' => $activity->id,
        ]);

    expect(LeadActivity::query()->where('lead_id', $lead->id)->count())->toBe(1);
});

test('the WhatsApp log API records a send without direction as outbound', function () {
    $lead = Lead::query()->create(['company_name' => 'Lead envio sem direction']);

    // Contrato atual do workflow de envio do n8n: message_id, status e message, sem direction.
    $activityId = $this->postJson("/api/prospects/{$lead->id}/whatsapp/log", whatsappLogPayload(), whatsappLogToken())
        ->assertCreated()
        ->json('activity_id');

    $activity = LeadActivity::query()->findOrFail($activityId);

    expect($activity->type)->toBe('whatsapp')
        ->and($activity->direction)->toBe(LeadActivity::DIRECTION_OUTBOUND)
        ->and($activity->message_status)->toBe('PENDING');
});

test('the WhatsApp log API records an explicit outbound message', function () {
    $lead = Lead::query()->create(['company_name' => 'Lead envio outbound']);

    $activityId = $this->postJson(
        "/api/prospects/{$lead->id}/whatsapp/log",
        whatsappLogPayload(['direction' => 'OUTBOUND', 'status' => 'SERVER']),
        whatsappLogToken(),
    )->assertCreated()->json('activity_id');

    $activity = LeadActivity::query()->findOrFail($activityId);

    expect($activity->direction)->toBe(LeadActivity::DIRECTION_OUTBOUND)
        ->and($activity->message_status)->toBe('SERVER');
});

test('the WhatsApp log API records a received message as inbound without a delivery status', function (?string $status) {
    $lead = Lead::query()->create([
        'company_name' => 'Lead recebimento',
        'status' => 'interested',
    ]);

    $payload = whatsappLogPayload([
        'message_id' => '3EB0C2EF5A6141DC0935D3',
        'direction' => 'inbound',
        'message' => 'Teste recebimento CRM 02',
    ]);

    if ($status === null) {
        unset($payload['status']);
    } else {
        $payload['status'] = $status;
    }

    $activityId = $this->postJson("/api/prospects/{$lead->id}/whatsapp/log", $payload, whatsappLogToken())
        ->assertCreated()
        ->assertJson([
            'success' => true,
            'message' => 'Mensagem recebida registrada.',
        ])
        ->json('activity_id');

    $activity = LeadActivity::query()->findOrFail($activityId);

    expect($activity->type)->toBe('whatsapp')
        ->and($activity->direction)->toBe(LeadActivity::DIRECTION_INBOUND)
        ->and($activity->message_status)->toBeNull()
        ->and($activity->lead_id)->toBe($lead->id)
        ->and($activity->description)->toBe('Teste recebimento CRM 02')
        ->and($activity->provider_message_id)->toBe('3EB0C2EF5A6141DC0935D3')
        ->and($activity->contacted_at)->not->toBeNull()
        ->and($activity->user_id)->toBeNull()
        ->and($activity->status)->toBeNull();

    expect($lead->fresh()->status)->toBe('interested')
        ->and($lead->fresh()->last_contacted_at?->toDateTimeString())->toBe($activity->contacted_at->toDateTimeString());
})->with([
    'without status' => [null],
    'with an artificial DEVICE status' => ['DEVICE'],
]);

test('the WhatsApp log API deduplicates a received message', function () {
    $lead = Lead::query()->create(['company_name' => 'Lead recebimento duplicado']);
    $payload = whatsappLogPayload(['direction' => 'inbound', 'message_id' => '3EB0INBOUNDDUP']);

    $activityId = $this->postJson("/api/prospects/{$lead->id}/whatsapp/log", $payload, whatsappLogToken())
        ->assertCreated()
        ->json('activity_id');

    $this->postJson("/api/prospects/{$lead->id}/whatsapp/log", $payload, whatsappLogToken())
        ->assertOk()
        ->assertJson(['idempotent' => true, 'activity_id' => $activityId]);

    expect(LeadActivity::query()->where('lead_id', $lead->id)->count())->toBe(1);
});

test('the WhatsApp log API rejects an invalid direction', function () {
    $lead = Lead::query()->create(['company_name' => 'Lead direction inválida']);

    $this->postJson(
        "/api/prospects/{$lead->id}/whatsapp/log",
        whatsappLogPayload(['direction' => 'sideways']),
        whatsappLogToken(),
    )->assertUnprocessable()->assertJsonValidationErrors('direction');

    expect(LeadActivity::query()->where('lead_id', $lead->id)->exists())->toBeFalse();
});

test('the WhatsApp log API still requires a status for outbound messages', function (array $overrides) {
    $lead = Lead::query()->create(['company_name' => 'Lead outbound sem status']);

    $payload = whatsappLogPayload($overrides);
    unset($payload['status']);

    $this->postJson("/api/prospects/{$lead->id}/whatsapp/log", $payload, whatsappLogToken())
        ->assertUnprocessable()
        ->assertJsonValidationErrors('status');
})->with([
    'without direction' => [[]],
    'explicit outbound' => [['direction' => 'outbound']],
]);

test('the WhatsApp log API rejects an inbound message without a valid token', function () {
    $lead = Lead::query()->create(['company_name' => 'Lead inbound sem token']);

    $this->postJson(
        "/api/prospects/{$lead->id}/whatsapp/log",
        whatsappLogPayload(['direction' => 'inbound']),
        ['Authorization' => 'Bearer token-errado'],
    )->assertUnauthorized();

    expect(LeadActivity::query()->where('lead_id', $lead->id)->exists())->toBeFalse();
});
