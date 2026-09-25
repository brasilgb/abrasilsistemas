<?php

use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\User;
use App\Services\CompanyWhatsappSettings;
use Illuminate\Support\Facades\DB;

function externalOutboundToken(): array
{
    return ['Authorization' => 'Bearer '.config('services.ab_prospect.token')];
}

function externalOutboundPayload(array $overrides = []): array
{
    return array_merge([
        'session' => 'vetoros1-1',
        'provider_message_id' => '3EB0C2EF5A6141DC0935D3',
        'to' => '555195179173@s.whatsapp.net',
        'message' => 'Teste crm-wa-06 enviado pelo celular',
        'status' => 'SERVER',
        'timestamp' => 1790281001,
    ], $overrides);
}

function postExternalOutbound(array $payload, ?array $headers = null)
{
    return test()->postJson('/api/whatsapp/outbound-external', $payload, $headers ?? externalOutboundToken());
}

function externalOutboundCompany(?string $session = 'vetoros1-1', ?string $number = '5551998931325'): void
{
    app(CompanyWhatsappSettings::class)->save([
        'enabled' => true,
        'number' => $number,
        'provider' => CompanyWhatsappSettings::PROVIDER_WAHA,
        'session' => $session,
    ]);
}

function externalOutboundLead(?string $whatsapp = '5551995179173', array $overrides = []): Lead
{
    return Lead::query()->create(array_merge([
        'company_name' => 'Lead outbound externo',
        'status' => 'contacted',
        'whatsapp' => $whatsapp,
    ], $overrides));
}

function logCrmSend(Lead $lead, string $messageId = '3EB0C2EF5A6141DC0935D3')
{
    return test()->postJson("/api/prospects/{$lead->id}/whatsapp/log", [
        'message_id' => $messageId,
        'status' => 'PENDING',
        'message' => 'Mensagem enviada pelo CRM.',
    ], externalOutboundToken());
}

beforeEach(function () {
    externalOutboundCompany();
});

test('the external outbound API requires the integration token', function (?string $token) {
    externalOutboundLead();

    postExternalOutbound(externalOutboundPayload(), $token === null ? [] : ['Authorization' => "Bearer {$token}"])
        ->assertUnauthorized();

    expect(LeadActivity::query()->exists())->toBeFalse();
})->with([
    'without a token' => [null],
    'with a wrong token' => ['token-errado'],
]);

test('the external outbound API records a message sent outside the CRM as outbound', function () {
    $lead = externalOutboundLead();

    $response = postExternalOutbound(externalOutboundPayload())
        ->assertCreated()
        ->assertJson([
            'success' => true,
            'message' => 'Mensagem enviada fora do CRM registrada.',
            'lead_id' => $lead->id,
        ]);

    $activity = LeadActivity::query()->findOrFail($response->json('activity_id'));

    expect($activity->lead_id)->toBe($lead->id)
        ->and($activity->type)->toBe('whatsapp')
        ->and($activity->direction)->toBe(LeadActivity::DIRECTION_OUTBOUND)
        ->and($activity->user_id)->toBeNull()
        ->and($activity->status)->toBeNull()
        ->and($activity->read_at)->toBeNull()
        ->and($activity->message_status)->toBe(LeadActivity::MESSAGE_STATUS_SERVER)
        ->and($activity->provider_message_id)->toBe('3EB0C2EF5A6141DC0935D3')
        ->and($activity->description)->toBe('Teste crm-wa-06 enviado pelo celular')
        ->and($activity->contacted_at)->not->toBeNull();

    expect($lead->fresh()->status)->toBe('contacted');
});

test('the external outbound API updates last_contacted_at', function () {
    $lead = externalOutboundLead(overrides: ['last_contacted_at' => now()->subDays(5)]);

    $activityId = postExternalOutbound(externalOutboundPayload())->assertCreated()->json('activity_id');

    expect($lead->fresh()->last_contacted_at->toDateTimeString())
        ->toBe(LeadActivity::query()->findOrFail($activityId)->contacted_at->toDateTimeString());
});

test('the external outbound API keeps a valid ACK status from the payload', function (string $status, string $expected) {
    externalOutboundLead();

    $activityId = postExternalOutbound(externalOutboundPayload(['status' => $status]))->assertCreated()->json('activity_id');

    expect(LeadActivity::query()->findOrFail($activityId)->message_status)->toBe($expected);
})->with([
    'DEVICE' => ['DEVICE', 'DEVICE'],
    'READ' => ['READ', 'READ'],
    'lowercase' => ['device', 'DEVICE'],
]);

test('the external outbound API falls back to SERVER without a valid status', function (?string $status) {
    externalOutboundLead();

    $payload = externalOutboundPayload(['status' => $status]);

    if ($status === null) {
        unset($payload['status']);
    }

    $activityId = postExternalOutbound($payload)->assertCreated()->json('activity_id');

    expect(LeadActivity::query()->findOrFail($activityId)->message_status)->toBe('SERVER');
})->with([
    'without status' => [null],
    'unknown status' => ['UNKNOWN'],
    'empty status' => [''],
]);

test('the external outbound API finds the lead by the recipient', function (string $to) {
    $lead = externalOutboundLead('5551995179173');
    externalOutboundLead('5511988887777', ['company_name' => 'Outro lead']);

    postExternalOutbound(externalOutboundPayload(['to' => $to]))
        ->assertCreated()
        ->assertJson(['lead_id' => $lead->id]);
})->with([
    's.whatsapp.net JID' => ['555195179173@s.whatsapp.net'],
    'c.us JID' => ['555195179173@c.us'],
    'plain phone' => ['555195179173'],
    'formatted phone' => ['+55 (51) 9517-9173'],
]);

test('the external outbound API matches Brazilian numbers with or without the ninth digit and country code', function (string $stored, string $to) {
    $lead = externalOutboundLead($stored);

    postExternalOutbound(externalOutboundPayload(['to' => $to]))
        ->assertCreated()
        ->assertJson(['lead_id' => $lead->id]);
})->with([
    'lead with ninth digit, WAHA without' => ['5551998931326', '555198931326@s.whatsapp.net'],
    'lead without ninth digit, WAHA with' => ['555198931326', '5551998931326@s.whatsapp.net'],
    'lead without country code' => ['51995179173', '555195179173@s.whatsapp.net'],
    'lead stored with a mask' => ['(51) 99517-9173', '555195179173@s.whatsapp.net'],
]);

test('the external outbound API does not create a lead or an activity for an unknown recipient', function () {
    $leads = Lead::query()->count();

    postExternalOutbound(externalOutboundPayload())
        ->assertOk()
        ->assertExactJson([
            'success' => false,
            'reason' => 'lead_not_found',
            'message' => 'Nenhum lead cadastrado com o número do destinatário.',
        ]);

    expect(Lead::query()->count())->toBe($leads)
        ->and(LeadActivity::query()->exists())->toBeFalse();
});

test('the external outbound API ignores a session other than the configured one', function () {
    externalOutboundLead();

    postExternalOutbound(externalOutboundPayload(['session' => 'outra-sessao']))
        ->assertOk()
        ->assertJson(['success' => false, 'reason' => 'session_mismatch']);

    expect(LeadActivity::query()->exists())->toBeFalse();
});

test('the external outbound API ignores messages while no company session is configured', function (bool $saved) {
    DB::table('settings')->delete();

    if ($saved) {
        externalOutboundCompany(session: null);
    }

    externalOutboundLead();

    postExternalOutbound(externalOutboundPayload())
        ->assertOk()
        ->assertJson(['success' => false, 'reason' => 'session_not_configured']);

    expect(LeadActivity::query()->exists())->toBeFalse();
})->with([
    'nothing saved' => [false],
    'saved without session' => [true],
]);

test('the external outbound API ignores a message sent to the company own number', function (string $to) {
    // Um lead com o número da empresa não pode receber a conversa consigo mesma.
    externalOutboundLead('5551998931325');

    postExternalOutbound(externalOutboundPayload(['to' => $to]))
        ->assertOk()
        ->assertJson(['success' => false, 'reason' => 'self_chat']);

    expect(LeadActivity::query()->exists())->toBeFalse();
})->with([
    'same number' => ['5551998931325@s.whatsapp.net'],
    'without the ninth digit' => ['555198931325@c.us'],
]);

test('the external outbound API rejects recipients that are not a phone', function (string $to) {
    externalOutboundLead();

    postExternalOutbound(externalOutboundPayload(['to' => $to]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('to');

    expect(LeadActivity::query()->exists())->toBeFalse();
})->with([
    'group' => ['120363025246125888@g.us'],
    'status broadcast' => ['status@broadcast'],
    'broadcast list' => ['1790281001@broadcast'],
    'newsletter' => ['120363223300000000@newsletter'],
    'unresolved LID' => ['185761748021363@lid'],
    'letters' => ['abc555195179173'],
    'too short' => ['51999'],
    'too long' => ['5551995179173123456'],
]);

test('the external outbound API rejects an invalid payload', function (array $overrides, string $field) {
    externalOutboundLead();

    postExternalOutbound(externalOutboundPayload($overrides))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);

    expect(LeadActivity::query()->exists())->toBeFalse();
})->with([
    'missing session' => [['session' => null], 'session'],
    'invalid session' => [['session' => 'sessão inválida'], 'session'],
    'missing provider_message_id' => [['provider_message_id' => null], 'provider_message_id'],
    'missing to' => [['to' => null], 'to'],
    'missing message' => [['message' => null], 'message'],
    'message as array' => [['message' => ['Teste']], 'message'],
    'status as array' => [['status' => ['SERVER']], 'status'],
    'timestamp as text' => [['timestamp' => 'ontem'], 'timestamp'],
]);

test('the external outbound API does not duplicate a repeated event', function () {
    $lead = externalOutboundLead();

    $activityId = postExternalOutbound(externalOutboundPayload())->assertCreated()->json('activity_id');

    postExternalOutbound(externalOutboundPayload(['message' => 'Reenvio do mesmo evento']))
        ->assertOk()
        ->assertJson([
            'success' => true,
            'reason' => 'already_logged',
            'activity_id' => $activityId,
            'lead_id' => $lead->id,
            'idempotent' => true,
        ]);

    expect(LeadActivity::query()->where('provider_message_id', '3EB0C2EF5A6141DC0935D3')->count())->toBe(1)
        ->and(LeadActivity::query()->findOrFail($activityId)->description)->toBe('Teste crm-wa-06 enviado pelo celular');
});

test('a CRM send logged first is not duplicated by the fromMe event', function () {
    $lead = externalOutboundLead();

    $activityId = logCrmSend($lead)->assertCreated()->json('activity_id');

    postExternalOutbound(externalOutboundPayload())
        ->assertOk()
        ->assertJson(['reason' => 'already_logged', 'activity_id' => $activityId, 'idempotent' => true]);

    $activity = LeadActivity::query()->where('provider_message_id', '3EB0C2EF5A6141DC0935D3')->sole();

    expect($activity->id)->toBe($activityId)
        ->and($activity->description)->toBe('Mensagem enviada pelo CRM.')
        ->and($activity->message_status)->toBe('PENDING');
});

test('a fromMe event recorded first is not duplicated by the later CRM log', function (bool $sameLead) {
    $lead = externalOutboundLead();
    // O operador pode enviar por outro lead que compartilha o número: a checagem do /log é global.
    $logLead = $sameLead ? $lead : externalOutboundLead('(51) 99517-9173', ['company_name' => 'Lead com o mesmo número']);

    $activityId = postExternalOutbound(externalOutboundPayload())->assertCreated()->json('activity_id');

    logCrmSend($logLead)
        ->assertOk()
        ->assertJson(['success' => true, 'idempotent' => true, 'activity_id' => $activityId]);

    expect(LeadActivity::query()->where('provider_message_id', '3EB0C2EF5A6141DC0935D3')->count())->toBe(1);
})->with([
    'same lead' => [true],
    'another lead with the same number' => [false],
]);

test('the CRM log and the external outbound API share the message lock', function () {
    $lead = externalOutboundLead();

    // Simula a requisição concorrente que já está gravando a mesma mensagem.
    $lock = LeadActivity::whatsappMessageLock('3EB0C2EF5A6141DC0935D3');
    expect($lock->get())->toBeTrue();

    postExternalOutbound(externalOutboundPayload())
        ->assertStatus(409)
        ->assertJson(['success' => false, 'reason' => 'processing']);

    logCrmSend($lead)
        ->assertStatus(409)
        ->assertJson(['success' => false, 'reason' => 'processing']);

    expect(LeadActivity::query()->exists())->toBeFalse();

    $lock->release();

    // O retry de cada lado depois do lock liberado resulta numa única atividade.
    $activityId = logCrmSend($lead)->assertCreated()->json('activity_id');
    postExternalOutbound(externalOutboundPayload())->assertOk()->assertJson(['activity_id' => $activityId]);

    expect(LeadActivity::query()->count())->toBe(1);
});

test('the shared message lock is the same one used by the inbound API', function () {
    $lock = LeadActivity::whatsappMessageLock('3EB00B502BEA742D043912');
    expect($lock->get())->toBeTrue();

    try {
        test()->postJson('/api/whatsapp/inbound', [
            'session' => 'vetoros1-1',
            'provider_message_id' => '3EB00B502BEA742D043912',
            'from' => '555195179173',
            'message' => 'Teste inbound',
        ], externalOutboundToken())->assertStatus(409);
    } finally {
        $lock->release();
    }
});

test('a later ACK updates the activity recorded by the external outbound API', function () {
    externalOutboundLead();

    $activityId = postExternalOutbound(externalOutboundPayload())->assertCreated()->json('activity_id');

    foreach (['DEVICE', 'READ', 'PLAYED'] as $status) {
        $this->patchJson('/api/whatsapp/messages/3EB0C2EF5A6141DC0935D3/status', ['status' => $status], externalOutboundToken())
            ->assertOk()
            ->assertJson(['activity_id' => $activityId, 'message_status' => $status]);
    }

    // Anti-regressão preservada.
    $this->patchJson('/api/whatsapp/messages/3EB0C2EF5A6141DC0935D3/status', ['status' => 'DEVICE'], externalOutboundToken())
        ->assertOk()
        ->assertJson(['ignored' => true, 'message_status' => 'PLAYED']);

    expect(LeadActivity::query()->count())->toBe(1)
        ->and(LeadActivity::query()->findOrFail($activityId)->message_status)->toBe('PLAYED');
});

test('the inbound API keeps working alongside the external outbound API', function () {
    $lead = externalOutboundLead();

    postExternalOutbound(externalOutboundPayload())->assertCreated();

    $inboundId = $this->postJson('/api/whatsapp/inbound', [
        'session' => 'vetoros1-1',
        'provider_message_id' => '3EB00B502BEA742D043912',
        'from' => '555195179173@s.whatsapp.net',
        'message' => 'Resposta do cliente',
    ], externalOutboundToken())->assertCreated()->assertJson(['lead_id' => $lead->id])->json('activity_id');

    // Um fromMe repassado por engano ao /inbound continua sem virar inbound.
    $this->postJson('/api/whatsapp/inbound', [
        'session' => 'vetoros1-1',
        'provider_message_id' => '3EB0C2EF5A6141DC0935D3',
        'from' => '555195179173@s.whatsapp.net',
        'message' => 'Teste crm-wa-06 enviado pelo celular',
    ], externalOutboundToken())->assertOk()->assertJson(['idempotent' => true]);

    expect(LeadActivity::query()->findOrFail($inboundId)->direction)->toBe(LeadActivity::DIRECTION_INBOUND)
        ->and($lead->activities()->where('direction', LeadActivity::DIRECTION_INBOUND)->count())->toBe(1)
        ->and($lead->activities()->where('direction', LeadActivity::DIRECTION_OUTBOUND)->count())->toBe(1);
});

test('the conversation returns the external message as outbound and markRead leaves it untouched', function () {
    $lead = externalOutboundLead();
    $user = User::factory()->create();

    $activityId = postExternalOutbound(externalOutboundPayload())->assertCreated()->json('activity_id');

    $this->actingAs($user)->getJson(route('leads.whatsapp.messages', $lead))
        ->assertOk()
        ->assertJsonCount(1, 'messages')
        ->assertJsonPath('messages.0.id', $activityId)
        ->assertJsonPath('messages.0.direction', LeadActivity::DIRECTION_OUTBOUND)
        ->assertJsonPath('messages.0.description', 'Teste crm-wa-06 enviado pelo celular')
        ->assertJsonPath('messages.0.message_status', 'SERVER')
        ->assertJsonPath('messages.0.read_at', null)
        ->assertJsonMissingPath('messages.0.provider_message_id');

    $this->actingAs($user)->postJson(route('leads.whatsapp.read', $lead), ['up_to_id' => $activityId])
        ->assertOk()
        ->assertJson(['marked' => 0]);

    $activity = LeadActivity::query()->findOrFail($activityId);

    expect($activity->read_at)->toBeNull()
        ->and($activity->message_status)->toBe('SERVER')
        ->and($lead->unreadWhatsappMessages()->count())->toBe(0);
});
