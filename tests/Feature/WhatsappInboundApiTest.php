<?php

use App\Models\Lead;
use App\Models\LeadActivity;
use App\Services\CompanyWhatsappSettings;

function whatsappInboundToken(): array
{
    return ['Authorization' => 'Bearer '.config('services.ab_prospect.token')];
}

function whatsappInboundPayload(array $overrides = []): array
{
    return array_merge([
        'session' => 'vetoros1-1',
        'provider_message_id' => '3EB00B502BEA742D043912',
        'from' => '555195179173',
        'push_name' => 'Anderson Brasil',
        'message' => 'Teste inbound 01',
    ], $overrides);
}

function postWhatsappInbound(array $payload, ?array $headers = null)
{
    return test()->postJson('/api/whatsapp/inbound', $payload, $headers ?? whatsappInboundToken());
}

function whatsappInboundLead(?string $whatsapp = '5551995179173', array $overrides = []): Lead
{
    return Lead::query()->create(array_merge([
        'company_name' => 'Lead inbound',
        'status' => 'contacted',
        'whatsapp' => $whatsapp,
    ], $overrides));
}

test('the WhatsApp inbound API requires the integration token', function (?string $token) {
    whatsappInboundLead();

    postWhatsappInbound(whatsappInboundPayload(), $token === null ? [] : ['Authorization' => "Bearer {$token}"])
        ->assertUnauthorized();

    expect(LeadActivity::query()->exists())->toBeFalse();
})->with([
    'without a token' => [null],
    'with a wrong token' => ['token-errado'],
]);

test('the WhatsApp inbound API rejects an invalid payload', function (array $overrides, string $field) {
    whatsappInboundLead();

    postWhatsappInbound(whatsappInboundPayload($overrides))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);

    expect(LeadActivity::query()->exists())->toBeFalse();
})->with([
    'missing session' => [['session' => null], 'session'],
    'invalid session' => [['session' => 'sessão inválida'], 'session'],
    'missing provider_message_id' => [['provider_message_id' => null], 'provider_message_id'],
    'missing from' => [['from' => null], 'from'],
    'LID as sender' => [['from' => '185761748021363@lid'], 'from'],
    'sender with letters' => [['from' => 'abc555195179173'], 'from'],
    'sender too short' => [['from' => '51999'], 'from'],
    'missing message' => [['message' => null], 'message'],
    'message as array' => [['message' => ['Teste']], 'message'],
    'push_name as array' => [['push_name' => ['Anderson']], 'push_name'],
]);

test('the WhatsApp inbound API records a received text message on the matching lead', function () {
    $lead = whatsappInboundLead('5551995179173');

    $response = postWhatsappInbound(whatsappInboundPayload())
        ->assertCreated()
        ->assertJson([
            'success' => true,
            'message' => 'Mensagem recebida registrada.',
            'lead_id' => $lead->id,
        ]);

    $activity = LeadActivity::query()->findOrFail($response->json('activity_id'));

    expect($activity->lead_id)->toBe($lead->id)
        ->and($activity->type)->toBe('whatsapp')
        ->and($activity->direction)->toBe(LeadActivity::DIRECTION_INBOUND)
        ->and($activity->message_status)->toBeNull()
        ->and($activity->user_id)->toBeNull()
        ->and($activity->status)->toBeNull()
        ->and($activity->provider_message_id)->toBe('3EB00B502BEA742D043912')
        ->and($activity->description)->toBe('Teste inbound 01')
        ->and($activity->contacted_at)->not->toBeNull();

    expect($lead->fresh()->status)->toBe('contacted')
        ->and($lead->fresh()->last_contacted_at)->not->toBeNull();
});

test('the WhatsApp inbound API accepts push_name as optional', function () {
    whatsappInboundLead();

    $payload = whatsappInboundPayload();
    unset($payload['push_name']);

    postWhatsappInbound($payload)->assertCreated();
});

test('the WhatsApp inbound API normalizes the sender before looking up the lead', function (string $from) {
    $lead = whatsappInboundLead('5551995179173');

    postWhatsappInbound(whatsappInboundPayload(['from' => $from]))
        ->assertCreated()
        ->assertJson(['lead_id' => $lead->id]);
})->with([
    'formatted' => ['+55 (51) 9517-9173'],
    'WhatsApp JID' => ['555195179173@s.whatsapp.net'],
    'c.us JID' => ['555195179173@c.us'],
]);

test('the WhatsApp inbound API matches Brazilian numbers with or without the ninth digit and country code', function (string $stored, string $from) {
    $lead = whatsappInboundLead($stored);

    postWhatsappInbound(whatsappInboundPayload(['from' => $from]))
        ->assertCreated()
        ->assertJson(['lead_id' => $lead->id]);
})->with([
    'lead with ninth digit, WAHA without' => ['5551998931325', '555198931325'],
    'lead without ninth digit, WAHA with' => ['555198931325', '5551998931325'],
    'lead without country code' => ['51995179173', '555195179173'],
    'lead stored with a mask' => ['(51) 99517-9173', '555195179173'],
    'same number' => ['555195179173', '555195179173'],
]);

test('the WhatsApp inbound API does not match a different number with the same ending', function () {
    whatsappInboundLead('5511995179173');

    postWhatsappInbound(whatsappInboundPayload())
        ->assertOk()
        ->assertJson(['success' => false, 'reason' => 'lead_not_found']);

    expect(LeadActivity::query()->exists())->toBeFalse();
});

test('the WhatsApp inbound API does not look up the lead by the LID or other contact fields', function () {
    Lead::query()->create([
        'company_name' => 'Lead só com telefone',
        'phone' => '5551995179173',
        'whatsapp' => null,
    ]);
    Lead::query()->create([
        'company_name' => 'Lead com o LID',
        'whatsapp' => '185761748021363',
    ]);

    postWhatsappInbound(whatsappInboundPayload())
        ->assertOk()
        ->assertJson(['success' => false, 'reason' => 'lead_not_found']);

    expect(LeadActivity::query()->exists())->toBeFalse();
});

test('the WhatsApp inbound API does not create a lead or an activity for an unknown sender', function () {
    $leads = Lead::query()->count();

    postWhatsappInbound(whatsappInboundPayload())
        ->assertOk()
        ->assertExactJson([
            'success' => false,
            'reason' => 'lead_not_found',
            'message' => 'Nenhum lead cadastrado com o número do remetente.',
        ]);

    expect(Lead::query()->count())->toBe($leads)
        ->and(LeadActivity::query()->exists())->toBeFalse();
});

test('the WhatsApp inbound API ignores soft deleted leads', function () {
    whatsappInboundLead()->delete();

    postWhatsappInbound(whatsappInboundPayload())
        ->assertOk()
        ->assertJson(['reason' => 'lead_not_found']);
});

test('the WhatsApp inbound API picks the most recently contacted lead when a number is shared', function () {
    whatsappInboundLead('5551995179173', ['company_name' => 'Antigo', 'last_contacted_at' => now()->subDays(10)]);
    $recent = whatsappInboundLead('51995179173', ['company_name' => 'Recente', 'last_contacted_at' => now()->subDay()]);

    postWhatsappInbound(whatsappInboundPayload())
        ->assertCreated()
        ->assertJson(['lead_id' => $recent->id]);
});

test('the WhatsApp inbound API does not duplicate a message with the same provider_message_id', function () {
    $lead = whatsappInboundLead();

    $activityId = postWhatsappInbound(whatsappInboundPayload())->assertCreated()->json('activity_id');

    postWhatsappInbound(whatsappInboundPayload(['message' => 'Reenvio do mesmo evento']))
        ->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Mensagem recebida já estava registrada.',
            'activity_id' => $activityId,
            'lead_id' => $lead->id,
            'idempotent' => true,
        ]);

    expect(LeadActivity::query()->where('provider_message_id', '3EB00B502BEA742D043912')->count())->toBe(1)
        ->and(LeadActivity::query()->findOrFail($activityId)->description)->toBe('Teste inbound 01');
});

test('the WhatsApp inbound API does not turn an already logged outbound message into an inbound one', function () {
    $lead = whatsappInboundLead();
    $outbound = $lead->activities()->create([
        'type' => 'whatsapp',
        'direction' => LeadActivity::DIRECTION_OUTBOUND,
        'contacted_at' => now(),
        'description' => 'Mensagem enviada pelo CRM.',
        'provider_message_id' => '3EB00B502BEA742D043912',
        'message_status' => 'DEVICE',
    ]);

    postWhatsappInbound(whatsappInboundPayload())
        ->assertOk()
        ->assertJson(['idempotent' => true, 'activity_id' => $outbound->id]);

    expect(LeadActivity::query()->count())->toBe(1)
        ->and($outbound->fresh()->direction)->toBe(LeadActivity::DIRECTION_OUTBOUND)
        ->and($outbound->fresh()->message_status)->toBe('DEVICE');
});

test('the WhatsApp inbound API only accepts the configured company session', function () {
    app(CompanyWhatsappSettings::class)->save([
        'enabled' => true,
        'number' => '5551998931325',
        'provider' => CompanyWhatsappSettings::PROVIDER_WAHA,
        'session' => 'vetoros1-1',
    ]);
    whatsappInboundLead();

    postWhatsappInbound(whatsappInboundPayload(['session' => 'outra-sessao']))
        ->assertOk()
        ->assertJson(['success' => false, 'reason' => 'session_mismatch']);

    expect(LeadActivity::query()->exists())->toBeFalse();

    postWhatsappInbound(whatsappInboundPayload())->assertCreated();
});

test('an ACK for a message received by the inbound API is ignored', function (string $status) {
    whatsappInboundLead();
    $activityId = postWhatsappInbound(whatsappInboundPayload())->assertCreated()->json('activity_id');

    $this->patchJson(
        '/api/whatsapp/messages/3EB00B502BEA742D043912/status',
        ['status' => $status],
        whatsappInboundToken(),
    )->assertOk()->assertJson(['ignored' => true, 'message_status' => null]);

    expect(LeadActivity::query()->findOrFail($activityId)->message_status)->toBeNull();
})->with(['SERVER', 'DEVICE', 'READ']);

test('the outbound log and ACK keep working alongside the inbound API', function () {
    $lead = whatsappInboundLead();

    postWhatsappInbound(whatsappInboundPayload())->assertCreated();

    $this->postJson("/api/prospects/{$lead->id}/whatsapp/log", [
        'message_id' => '3EB0F27EBBA9D67C6210DD',
        'status' => 'PENDING',
        'message' => 'Resposta do CRM.',
    ], whatsappInboundToken())->assertCreated();

    $this->patchJson(
        '/api/whatsapp/messages/3EB0F27EBBA9D67C6210DD/status',
        ['status' => 'DEVICE'],
        whatsappInboundToken(),
    )->assertOk()->assertJson(['message_status' => 'DEVICE']);

    $outbound = LeadActivity::query()->where('provider_message_id', '3EB0F27EBBA9D67C6210DD')->sole();

    expect($outbound->direction)->toBe(LeadActivity::DIRECTION_OUTBOUND)
        ->and($outbound->message_status)->toBe('DEVICE')
        ->and($lead->activities()->where('direction', LeadActivity::DIRECTION_INBOUND)->count())->toBe(1);
});
