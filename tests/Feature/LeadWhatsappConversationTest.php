<?php

use App\Http\Controllers\LeadWhatsappConversationController;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

function conversationLead(array $overrides = []): Lead
{
    return Lead::query()->create(array_merge([
        'company_name' => 'Lead com conversa',
        'product' => 'site',
        'status' => 'contacted',
        'whatsapp' => '51999998888',
    ], $overrides));
}

function conversationMessage(Lead $lead, string $direction, array $overrides = []): LeadActivity
{
    $createdAt = $overrides['created_at'] ?? null;
    unset($overrides['created_at']);

    $activity = $lead->activities()->create(array_merge([
        'type' => 'whatsapp',
        'direction' => $direction,
        'contacted_at' => now(),
        'description' => $direction === LeadActivity::DIRECTION_INBOUND ? 'Mensagem do cliente.' : 'Mensagem da empresa.',
        'provider_message_id' => 'msg_'.fake()->unique()->uuid(),
        'message_status' => $direction === LeadActivity::DIRECTION_OUTBOUND ? LeadActivity::MESSAGE_STATUS_SERVER : null,
    ], $overrides));

    if ($createdAt !== null) {
        $activity->forceFill(['created_at' => $createdAt])->save();
    }

    return $activity->refresh();
}

function conversationProspectToken(): array
{
    return ['Authorization' => 'Bearer '.config('services.ab_prospect.token')];
}

function markConversationRead(Lead $lead, mixed $upToId)
{
    return test()->postJson(route('leads.whatsapp.read', $lead), ['up_to_id' => $upToId]);
}

test('the conversation endpoints require authentication', function () {
    $lead = conversationLead();
    $inbound = conversationMessage($lead, LeadActivity::DIRECTION_INBOUND);

    $this->get(route('leads.whatsapp.messages', $lead))->assertRedirect(route('login'));
    $this->post(route('leads.whatsapp.read', $lead), ['up_to_id' => $inbound->id])->assertRedirect(route('login'));

    expect($inbound->refresh()->read_at)->toBeNull();
});

test('the conversation endpoints require an admin user', function () {
    $lead = conversationLead();
    $inbound = conversationMessage($lead, LeadActivity::DIRECTION_INBOUND);

    $this->actingAs(User::factory()->reader()->create());

    $this->getJson(route('leads.whatsapp.messages', $lead))->assertForbidden();
    markConversationRead($lead, $inbound->id)->assertForbidden();

    expect($inbound->refresh()->read_at)->toBeNull();
});

test('the conversation only returns WhatsApp messages with a direction', function () {
    $user = User::factory()->create();
    $lead = conversationLead();

    $inbound = conversationMessage($lead, LeadActivity::DIRECTION_INBOUND);
    $outbound = conversationMessage($lead, LeadActivity::DIRECTION_OUTBOUND);
    // WhatsApp registrado manualmente em "Registrar contato": sem direction, fica só no histórico.
    $lead->activities()->create([
        'user_id' => $user->id,
        'type' => 'whatsapp',
        'contacted_at' => now(),
        'description' => 'Conversei pelo WhatsApp.',
    ]);
    $lead->activities()->create(['type' => 'note', 'description' => 'Nota.']);
    $lead->activities()->create(['type' => 'call', 'direction' => LeadActivity::DIRECTION_INBOUND, 'description' => 'Ligação.']);

    $this->actingAs($user)->getJson(route('leads.whatsapp.messages', $lead))
        ->assertOk()
        ->assertJsonCount(2, 'messages')
        ->assertJsonPath('messages.*.id', [$inbound->id, $outbound->id]);
});

test('the conversation only returns messages of the route lead', function () {
    $user = User::factory()->create();
    $lead = conversationLead();
    $other = conversationLead(['company_name' => 'Outro lead', 'whatsapp' => '51988887777']);

    $mine = conversationMessage($lead, LeadActivity::DIRECTION_INBOUND);
    conversationMessage($other, LeadActivity::DIRECTION_INBOUND);
    conversationMessage($other, LeadActivity::DIRECTION_OUTBOUND);

    $this->actingAs($user)->getJson(route('leads.whatsapp.messages', $lead))
        ->assertOk()
        ->assertJsonPath('messages.*.id', [$mine->id]);
});

test('the conversation is ordered by created_at and id ascending', function () {
    $user = User::factory()->create();
    $lead = conversationLead();
    $second = Carbon::parse('2026-09-24 10:00:05');

    $late = conversationMessage($lead, LeadActivity::DIRECTION_INBOUND, ['created_at' => Carbon::parse('2026-09-24 10:00:09')]);
    $sameSecondA = conversationMessage($lead, LeadActivity::DIRECTION_OUTBOUND, ['created_at' => $second]);
    $sameSecondB = conversationMessage($lead, LeadActivity::DIRECTION_INBOUND, ['created_at' => $second]);
    $early = conversationMessage($lead, LeadActivity::DIRECTION_OUTBOUND, ['created_at' => Carbon::parse('2026-09-24 09:59:00')]);

    $this->actingAs($user)->getJson(route('leads.whatsapp.messages', $lead))
        ->assertOk()
        ->assertJsonPath('messages.*.id', [$early->id, $sameSecondA->id, $sameSecondB->id, $late->id]);
});

test('the conversation returns only the latest 100 messages in ascending order', function () {
    $user = User::factory()->create();
    $lead = conversationLead();
    $start = Carbon::parse('2026-09-24 08:00:00');
    $limit = LeadWhatsappConversationController::MESSAGES_LIMIT;

    $ids = collect(range(1, $limit + 5))->map(fn (int $minute) => conversationMessage(
        $lead,
        $minute % 2 ? LeadActivity::DIRECTION_INBOUND : LeadActivity::DIRECTION_OUTBOUND,
        ['created_at' => $start->copy()->addMinutes($minute)],
    )->id);

    $this->actingAs($user)->getJson(route('leads.whatsapp.messages', $lead))
        ->assertOk()
        ->assertJsonCount($limit, 'messages')
        ->assertJsonPath('messages.*.id', $ids->slice(5)->values()->all());
});

test('the conversation exposes only the message fields and never the provider message id', function () {
    $user = User::factory()->create();
    $lead = conversationLead();
    conversationMessage($lead, LeadActivity::DIRECTION_OUTBOUND, ['user_id' => $user->id, 'provider_message_id' => 'true_5551_SECRET']);

    $response = $this->actingAs($user)->getJson(route('leads.whatsapp.messages', $lead))->assertOk();

    expect(array_keys($response->json('messages.0')))
        ->toEqualCanonicalizing(['id', 'direction', 'description', 'created_at', 'message_status', 'read_at'])
        ->and($response->getContent())->not->toContain('true_5551_SECRET');
});

test('reading the conversation has no side effects', function () {
    $user = User::factory()->create();
    $lead = conversationLead();
    $inbound = conversationMessage($lead, LeadActivity::DIRECTION_INBOUND);
    $outbound = conversationMessage($lead, LeadActivity::DIRECTION_OUTBOUND);
    $before = [$inbound->getAttributes(), $outbound->getAttributes(), $lead->refresh()->getAttributes()];

    $this->actingAs($user)->getJson(route('leads.whatsapp.messages', $lead))
        ->assertOk()
        ->assertJsonPath('messages.0.read_at', null);

    expect([$inbound->refresh()->getAttributes(), $outbound->refresh()->getAttributes(), $lead->refresh()->getAttributes()])
        ->toBe($before)
        ->and($lead->unreadWhatsappMessages()->count())->toBe(1);
});

test('markRead only marks unread inbound messages of the lead up to the given id', function () {
    $user = User::factory()->create();
    $lead = conversationLead();
    $alreadyRead = conversationMessage($lead, LeadActivity::DIRECTION_INBOUND, ['read_at' => Carbon::parse('2026-09-20 12:00:00')]);
    $first = conversationMessage($lead, LeadActivity::DIRECTION_INBOUND);
    $second = conversationMessage($lead, LeadActivity::DIRECTION_INBOUND);
    $later = conversationMessage($lead, LeadActivity::DIRECTION_INBOUND);

    $this->actingAs($user);
    markConversationRead($lead, $second->id)->assertOk()->assertExactJson(['marked' => 2]);

    expect($first->refresh()->read_at)->not->toBeNull()
        ->and($second->refresh()->read_at)->not->toBeNull()
        ->and($later->refresh()->read_at)->toBeNull()
        ->and($alreadyRead->refresh()->read_at->toDateTimeString())->toBe('2026-09-20 12:00:00');
});

test('markRead does not touch outbound messages', function () {
    $user = User::factory()->create();
    $lead = conversationLead();
    $outbound = conversationMessage($lead, LeadActivity::DIRECTION_OUTBOUND);
    $inbound = conversationMessage($lead, LeadActivity::DIRECTION_INBOUND);

    $this->actingAs($user);
    markConversationRead($lead, $inbound->id)->assertOk()->assertExactJson(['marked' => 1]);

    expect($outbound->refresh()->read_at)->toBeNull()
        ->and($outbound->message_status)->toBe(LeadActivity::MESSAGE_STATUS_SERVER);
});

test('markRead does not touch messages of other leads', function () {
    $user = User::factory()->create();
    $lead = conversationLead();
    $other = conversationLead(['company_name' => 'Outro lead', 'whatsapp' => '51988887777']);
    $othersInbound = conversationMessage($other, LeadActivity::DIRECTION_INBOUND);
    $inbound = conversationMessage($lead, LeadActivity::DIRECTION_INBOUND);

    $this->actingAs($user);
    // up_to_id acima do id do outro lead: mesmo assim, só o lead da rota é marcado.
    markConversationRead($lead, $inbound->id)->assertOk()->assertExactJson(['marked' => 1]);

    expect($othersInbound->refresh()->read_at)->toBeNull()
        ->and($other->unreadWhatsappMessages()->count())->toBe(1);
});

test('markRead never changes the message status', function () {
    $user = User::factory()->create();
    $lead = conversationLead();
    // Inbound com um message_status atípico: a leitura interna não deve mexer nele.
    $inbound = conversationMessage($lead, LeadActivity::DIRECTION_INBOUND, ['message_status' => LeadActivity::MESSAGE_STATUS_DEVICE]);
    $outbound = conversationMessage($lead, LeadActivity::DIRECTION_OUTBOUND, ['message_status' => LeadActivity::MESSAGE_STATUS_PENDING]);

    $this->actingAs($user);
    markConversationRead($lead, $outbound->id)->assertOk();

    expect($inbound->refresh()->read_at)->not->toBeNull()
        ->and($inbound->message_status)->toBe(LeadActivity::MESSAGE_STATUS_DEVICE)
        ->and($outbound->refresh()->message_status)->toBe(LeadActivity::MESSAGE_STATUS_PENDING);
});

test('markRead is idempotent', function () {
    $user = User::factory()->create();
    $lead = conversationLead();
    $inbound = conversationMessage($lead, LeadActivity::DIRECTION_INBOUND);

    $this->actingAs($user);
    $this->travelTo(Carbon::parse('2026-09-24 10:00:00'));
    markConversationRead($lead, $inbound->id)->assertOk()->assertExactJson(['marked' => 1]);

    $this->travelTo(Carbon::parse('2026-09-24 11:00:00'));
    markConversationRead($lead, $inbound->id)->assertOk()->assertExactJson(['marked' => 0]);

    expect($inbound->refresh()->read_at->toDateTimeString())->toBe('2026-09-24 10:00:00');
});

test('markRead validates up_to_id', function (mixed $upToId) {
    $user = User::factory()->create();
    $lead = conversationLead();
    $inbound = conversationMessage($lead, LeadActivity::DIRECTION_INBOUND);

    $this->actingAs($user);
    markConversationRead($lead, $upToId)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('up_to_id');

    expect($inbound->refresh()->read_at)->toBeNull();
})->with([
    'missing' => [null],
    'text' => ['abc'],
    'decimal' => [1.5],
    'zero' => [0],
    'negative' => [-1],
]);

test('a later ACK update is reflected in the conversation', function () {
    $user = User::factory()->create();
    $lead = conversationLead();
    $outbound = conversationMessage($lead, LeadActivity::DIRECTION_OUTBOUND, [
        'provider_message_id' => '3EB05962A724B2EB80AD88',
        'message_status' => LeadActivity::MESSAGE_STATUS_PENDING,
    ]);

    $this->actingAs($user)->getJson(route('leads.whatsapp.messages', $lead))
        ->assertJsonPath('messages.0.message_status', LeadActivity::MESSAGE_STATUS_PENDING);

    $this->patchJson('/api/whatsapp/messages/3EB05962A724B2EB80AD88/status', ['status' => 'READ'], conversationProspectToken())
        ->assertSuccessful();

    $this->getJson(route('leads.whatsapp.messages', $lead))
        ->assertJsonCount(1, 'messages')
        ->assertJsonPath('messages.0.id', $outbound->id)
        ->assertJsonPath('messages.0.message_status', LeadActivity::MESSAGE_STATUS_READ);
});

test('an activity logged twice through /log with the same message id appears only once', function () {
    $user = User::factory()->create();
    $lead = conversationLead();
    $payload = ['message_id' => '3EB0F27EBBA9D67C6210DD', 'status' => 'PENDING', 'message' => 'Olá, tudo bem?'];

    $this->postJson("/api/prospects/{$lead->id}/whatsapp/log", $payload, conversationProspectToken())->assertSuccessful();
    $this->postJson("/api/prospects/{$lead->id}/whatsapp/log", $payload, conversationProspectToken())->assertSuccessful();

    $this->actingAs($user)->getJson(route('leads.whatsapp.messages', $lead))
        ->assertOk()
        ->assertJsonCount(1, 'messages')
        ->assertJsonPath('messages.0.direction', LeadActivity::DIRECTION_OUTBOUND)
        ->assertJsonPath('messages.0.description', 'Olá, tudo bem?');
});

test('opening the lead still marks its pending inbound messages as read', function () {
    $user = User::factory()->create();
    $lead = conversationLead();
    $inbound = conversationMessage($lead, LeadActivity::DIRECTION_INBOUND);

    $this->actingAs($user)->get(route('leads.edit', $lead))->assertOk();

    expect($inbound->refresh()->read_at)->not->toBeNull();

    $this->getJson(route('leads.whatsapp.messages', $lead))
        ->assertJsonPath('messages.0.id', $inbound->id)
        ->assertJsonPath('messages.0.read_at', fn ($readAt) => $readAt !== null);
});

test('sending through the existing route still relies on n8n to register the message', function () {
    config([
        'services.n8n.whatsapp_webhook_url' => 'https://n8n.test/webhook/crm-enviar-whatsapp',
        'services.n8n.whatsapp_webhook_header' => 'X-CRM-Token',
        'services.n8n.whatsapp_webhook_token' => 'n8n-token',
    ]);
    Http::preventStrayRequests();
    Http::fake(['https://n8n.test/*' => Http::response([
        'success' => true,
        'message_id' => '3EB05962A724B2EB80AD88',
        'status' => 'PENDING',
        'activity_id' => 1,
    ])]);
    $user = User::factory()->create();
    $lead = conversationLead();

    $this->actingAs($user)
        ->from(route('leads.edit', $lead))
        ->post(route('leads.whatsapp.store', $lead), ['message' => 'Olá pela conversa.'])
        ->assertRedirect(route('leads.edit', $lead))
        ->assertSessionHasNoErrors();

    Http::assertSentCount(1);
    expect(LeadActivity::query()->count())->toBe(0);

    $this->getJson(route('leads.whatsapp.messages', $lead))->assertJsonCount(0, 'messages');
});
