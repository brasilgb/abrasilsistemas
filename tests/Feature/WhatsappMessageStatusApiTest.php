<?php

use App\Models\Lead;
use App\Models\LeadActivity;

function whatsappStatusToken(): array
{
    return ['Authorization' => 'Bearer '.config('services.ab_prospect.token')];
}

function whatsappStatusActivity(?string $messageStatus = 'PENDING', array $overrides = []): LeadActivity
{
    $lead = Lead::query()->create([
        'company_name' => 'Lead ACK',
        'status' => 'interested',
    ]);

    return $lead->activities()->create(array_merge([
        'user_id' => null,
        'type' => 'whatsapp',
        'status' => null,
        'contacted_at' => now(),
        'description' => 'Mensagem enviada.',
        'provider_message_id' => '3EB05962A724B2EB80AD88',
        'message_status' => $messageStatus,
    ], $overrides));
}

function patchWhatsappStatus(string $status, string $messageId = '3EB05962A724B2EB80AD88', ?array $headers = null)
{
    return test()->patchJson(
        "/api/whatsapp/messages/{$messageId}/status",
        ['status' => $status],
        $headers ?? whatsappStatusToken(),
    );
}

test('the WhatsApp status API rejects requests without a token', function () {
    $activity = whatsappStatusActivity();

    patchWhatsappStatus('DEVICE', headers: [])->assertUnauthorized();

    expect($activity->fresh()->message_status)->toBe('PENDING');
});

test('the WhatsApp status API rejects requests with an invalid token', function () {
    $activity = whatsappStatusActivity();

    patchWhatsappStatus('DEVICE', headers: ['Authorization' => 'Bearer wrong-token'])->assertUnauthorized();

    expect($activity->fresh()->message_status)->toBe('PENDING');
});

test('the WhatsApp status API rejects an invalid payload', function (array $payload) {
    $activity = whatsappStatusActivity();

    $this->patchJson('/api/whatsapp/messages/3EB05962A724B2EB80AD88/status', $payload, whatsappStatusToken())
        ->assertUnprocessable()
        ->assertJsonValidationErrors('status');

    expect($activity->fresh()->message_status)->toBe('PENDING');
})->with([
    'missing status' => [[]],
    'unknown status' => [['status' => 'DELIVERED']],
    'commercial status' => [['status' => 'interested']],
    'non string status' => [['status' => 2]],
]);

test('the WhatsApp status API returns not found for an unknown message', function () {
    patchWhatsappStatus('DEVICE', 'UNKNOWN_ID')
        ->assertNotFound()
        ->assertJson(['success' => false]);

    expect(LeadActivity::query()->count())->toBe(0);
});

test('the WhatsApp status API does not update non WhatsApp activities', function () {
    $activity = whatsappStatusActivity('PENDING', ['type' => 'email']);

    patchWhatsappStatus('DEVICE')->assertNotFound();

    expect($activity->fresh()->message_status)->toBe('PENDING');
});

test('the WhatsApp status API advances the ACK progression', function (string $from, string $to) {
    $activity = whatsappStatusActivity($from);

    patchWhatsappStatus($to)
        ->assertOk()
        ->assertExactJson([
            'success' => true,
            'message' => 'Status da mensagem atualizado.',
            'activity_id' => $activity->id,
            'message_status' => $to,
        ]);

    expect($activity->fresh()->message_status)->toBe($to);
})->with([
    'PENDING -> SERVER' => ['PENDING', 'SERVER'],
    'SERVER -> DEVICE' => ['SERVER', 'DEVICE'],
    'DEVICE -> READ' => ['DEVICE', 'READ'],
    'READ -> PLAYED' => ['READ', 'PLAYED'],
    'PENDING -> READ (skipped ACKs)' => ['PENDING', 'READ'],
]);

test('the WhatsApp status API accepts a lowercase status', function () {
    $activity = whatsappStatusActivity('PENDING');

    patchWhatsappStatus('device')->assertOk()->assertJson(['message_status' => 'DEVICE']);

    expect($activity->fresh()->message_status)->toBe('DEVICE');
});

test('the WhatsApp status API does not regress the ACK status', function (string $from, string $to) {
    $activity = whatsappStatusActivity($from);

    patchWhatsappStatus($to)
        ->assertOk()
        ->assertJson([
            'success' => true,
            'activity_id' => $activity->id,
            'message_status' => $from,
            'ignored' => true,
        ]);

    expect($activity->fresh()->message_status)->toBe($from);
})->with([
    'READ -> DEVICE' => ['READ', 'DEVICE'],
    'DEVICE -> SERVER' => ['DEVICE', 'SERVER'],
    'READ -> PENDING' => ['READ', 'PENDING'],
    'PLAYED -> READ' => ['PLAYED', 'READ'],
]);

test('the WhatsApp status API is idempotent for the same status', function () {
    $activity = whatsappStatusActivity('DEVICE');

    patchWhatsappStatus('DEVICE')
        ->assertOk()
        ->assertExactJson([
            'success' => true,
            'message' => 'Status já estava atualizado.',
            'activity_id' => $activity->id,
            'message_status' => 'DEVICE',
            'idempotent' => true,
        ]);

    expect($activity->fresh()->message_status)->toBe('DEVICE');
});

test('the WhatsApp status API only changes the message status', function () {
    $activity = whatsappStatusActivity('PENDING', [
        'status' => 'contacted',
        'next_follow_up_at' => '2026-10-01',
    ]);
    $lead = $activity->lead;
    $before = $activity->fresh()->only([
        'status', 'contacted_at', 'next_follow_up_at', 'description', 'user_id', 'provider_message_id',
    ]);
    $leadBefore = $lead->fresh()->only(['status', 'last_contacted_at']);

    patchWhatsappStatus('READ')->assertOk();

    $activity->refresh();

    expect($activity->message_status)->toBe('READ')
        ->and($activity->only(array_keys($before)))->toEqual($before)
        ->and($activity->status)->toBe('contacted')
        ->and($lead->fresh()->only(['status', 'last_contacted_at']))->toEqual($leadBefore)
        ->and($lead->fresh()->status)->toBe('interested');
});

test('the WhatsApp status API accepts ERROR before device confirmation', function (string $from) {
    $activity = whatsappStatusActivity($from);

    patchWhatsappStatus('ERROR')->assertOk()->assertJson(['message_status' => 'ERROR']);

    expect($activity->fresh()->message_status)->toBe('ERROR');
})->with(['PENDING', 'SERVER']);

test('the WhatsApp status API ignores ERROR after device confirmation', function (string $from) {
    $activity = whatsappStatusActivity($from);

    patchWhatsappStatus('ERROR')->assertOk()->assertJson(['ignored' => true, 'message_status' => $from]);

    expect($activity->fresh()->message_status)->toBe($from);
})->with(['DEVICE', 'READ', 'PLAYED']);

test('the WhatsApp status API lets a positive ACK override ERROR but not PENDING', function () {
    $activity = whatsappStatusActivity('ERROR');

    patchWhatsappStatus('PENDING')->assertOk()->assertJson(['ignored' => true, 'message_status' => 'ERROR']);
    expect($activity->fresh()->message_status)->toBe('ERROR');

    patchWhatsappStatus('DEVICE')->assertOk()->assertJson(['message_status' => 'DEVICE']);
    expect($activity->fresh()->message_status)->toBe('DEVICE');
});

test('the WhatsApp status API accepts any valid status when none is set', function () {
    $activity = whatsappStatusActivity(null);

    patchWhatsappStatus('SERVER')->assertOk()->assertJson(['message_status' => 'SERVER']);

    expect($activity->fresh()->message_status)->toBe('SERVER');
});
