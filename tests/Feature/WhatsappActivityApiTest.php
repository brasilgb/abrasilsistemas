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
