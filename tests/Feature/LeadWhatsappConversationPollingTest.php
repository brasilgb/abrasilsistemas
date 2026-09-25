<?php

use App\Http\Controllers\LeadWhatsappConversationController;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\User;
use App\Services\CompanyWhatsappSettings;
use Illuminate\Support\Carbon;

function pollingLead(array $overrides = []): Lead
{
    return Lead::query()->create(array_merge([
        'company_name' => 'Lead da conversa operacional',
        'product' => 'site',
        'status' => 'contacted',
        'whatsapp' => '51999997777',
    ], $overrides));
}

function pollingMessage(Lead $lead, string $direction, array $overrides = []): LeadActivity
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
        $activity->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();
    }

    return $activity->refresh();
}

function pollingToken(): array
{
    return ['Authorization' => 'Bearer '.config('services.ab_prospect.token')];
}

function messagesUrl(Lead $lead, array $query = []): string
{
    return route('leads.whatsapp.messages', ['lead' => $lead, ...$query]);
}

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-09-25 10:00:00'));
});

test('an authorized user reads the conversation with the pagination and polling metadata', function () {
    $lead = pollingLead();
    $inbound = pollingMessage($lead, LeadActivity::DIRECTION_INBOUND);

    $this->actingAs(User::factory()->create())
        ->getJson(messagesUrl($lead))
        ->assertOk()
        ->assertJsonPath('messages.*.id', [$inbound->id])
        ->assertJsonPath('has_more', false)
        ->assertJsonPath('cursor', now()->subSeconds(LeadWhatsappConversationController::CURSOR_OVERLAP_SECONDS)->getTimestamp());
});

test('an unauthorized user cannot read the conversation in any mode', function (array $query) {
    $lead = pollingLead();
    $inbound = pollingMessage($lead, LeadActivity::DIRECTION_INBOUND);
    $query = array_map(fn ($value) => $value === 'inbound' ? $inbound->id : $value, $query);

    $this->getJson(messagesUrl($lead, $query))->assertUnauthorized();

    $this->actingAs(User::factory()->reader()->create())
        ->getJson(messagesUrl($lead, $query))
        ->assertForbidden();
})->with([
    'latest' => [[]],
    'older page' => [['before_id' => 'inbound']],
    'polling' => [['since' => 0]],
]);

test('inbound and outbound keep their direction and outbound returns its ACK status', function () {
    $lead = pollingLead();
    $inbound = pollingMessage($lead, LeadActivity::DIRECTION_INBOUND, ['created_at' => now()->subMinutes(3)]);
    $pending = pollingMessage($lead, LeadActivity::DIRECTION_OUTBOUND, ['message_status' => LeadActivity::MESSAGE_STATUS_PENDING, 'created_at' => now()->subMinutes(2)]);
    $delivered = pollingMessage($lead, LeadActivity::DIRECTION_OUTBOUND, ['message_status' => LeadActivity::MESSAGE_STATUS_DEVICE, 'created_at' => now()->subMinute()]);
    $failed = pollingMessage($lead, LeadActivity::DIRECTION_OUTBOUND, ['message_status' => LeadActivity::MESSAGE_STATUS_ERROR]);

    $this->actingAs(User::factory()->create())
        ->getJson(messagesUrl($lead))
        ->assertOk()
        ->assertJsonPath('messages.*.id', [$inbound->id, $pending->id, $delivered->id, $failed->id])
        ->assertJsonPath('messages.*.direction', ['inbound', 'outbound', 'outbound', 'outbound'])
        ->assertJsonPath('messages.*.message_status', [null, 'PENDING', 'DEVICE', 'ERROR']);
});

test('an empty conversation returns no messages in every mode', function () {
    $lead = pollingLead();
    $this->actingAs(User::factory()->create());

    $this->getJson(messagesUrl($lead))
        ->assertOk()
        ->assertJsonPath('messages', [])
        ->assertJsonPath('has_more', false);

    $this->getJson(messagesUrl($lead, ['since' => 0]))
        ->assertOk()
        ->assertJsonPath('messages', [])
        ->assertJsonPath('reset', false);
});

test('older pages are fetched with before_id without duplicates or gaps', function () {
    $lead = pollingLead();
    $limit = LeadWhatsappConversationController::MESSAGES_LIMIT;
    $start = now()->subDays(3);
    // Dois registros no mesmo segundo na fronteira entre páginas: o desempate é pelo id.
    $ids = collect(range(1, $limit * 2 + 7))->map(fn (int $i) => pollingMessage(
        $lead,
        $i % 2 ? LeadActivity::DIRECTION_INBOUND : LeadActivity::DIRECTION_OUTBOUND,
        ['created_at' => $start->copy()->addMinutes(intdiv($i, 2))],
    )->id);

    $this->actingAs(User::factory()->create());

    $latest = $this->getJson(messagesUrl($lead))->assertOk()->assertJsonPath('has_more', true);
    $middle = $this->getJson(messagesUrl($lead, ['before_id' => $latest->json('messages.0.id')]))->assertOk()->assertJsonPath('has_more', true);
    $oldest = $this->getJson(messagesUrl($lead, ['before_id' => $middle->json('messages.0.id')]))->assertOk()->assertJsonPath('has_more', false);

    $seen = collect([...$oldest->json('messages.*.id'), ...$middle->json('messages.*.id'), ...$latest->json('messages.*.id')]);

    expect($latest->json('messages'))->toHaveCount($limit)
        ->and($middle->json('messages'))->toHaveCount($limit)
        ->and($oldest->json('messages'))->toHaveCount(7)
        ->and($seen->duplicates())->toBeEmpty()
        ->and($seen->all())->toBe($ids->all());
});

test('before_id must be a message of the conversation of the route lead', function (string $case) {
    $lead = pollingLead();
    $other = pollingLead(['company_name' => 'Outro lead', 'whatsapp' => '51988886666']);
    pollingMessage($lead, LeadActivity::DIRECTION_INBOUND);

    $beforeId = match ($case) {
        'other lead' => pollingMessage($other, LeadActivity::DIRECTION_INBOUND)->id,
        'note' => $lead->activities()->create(['type' => 'note', 'description' => 'Nota.'])->id,
        'missing' => 999999,
        'text' => 'abc',
        'zero' => 0,
    };

    $this->actingAs(User::factory()->create())
        ->getJson(messagesUrl($lead, ['before_id' => $beforeId]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('before_id');
})->with(['other lead', 'note', 'missing', 'text', 'zero']);

test('since is validated and cannot be combined with before_id', function () {
    $lead = pollingLead();
    $inbound = pollingMessage($lead, LeadActivity::DIRECTION_INBOUND);
    $this->actingAs(User::factory()->create());

    $this->getJson(messagesUrl($lead, ['since' => 'ontem']))->assertUnprocessable()->assertJsonValidationErrors('since');
    $this->getJson(messagesUrl($lead, ['since' => -1]))->assertUnprocessable()->assertJsonValidationErrors('since');
    $this->getJson(messagesUrl($lead, ['since' => 0, 'before_id' => $inbound->id]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['since', 'before_id']);
});

test('incremental polling returns only messages created after the cursor', function () {
    $lead = pollingLead();
    $old = pollingMessage($lead, LeadActivity::DIRECTION_INBOUND, ['created_at' => now()->subMinutes(10)]);
    $user = User::factory()->create();

    $cursor = $this->actingAs($user)->getJson(messagesUrl($lead))->assertOk()->json('cursor');

    $this->travel(5)->seconds();
    $arrived = pollingMessage($lead, LeadActivity::DIRECTION_OUTBOUND, ['description' => 'Enviada pelo celular']);
    // Outro lead e atividade fora da conversa não entram.
    pollingMessage(pollingLead(['company_name' => 'Outro', 'whatsapp' => '51988885555']), LeadActivity::DIRECTION_INBOUND);
    $lead->activities()->create(['type' => 'note', 'description' => 'Nota.']);

    $response = $this->getJson(messagesUrl($lead, ['since' => $cursor]))
        ->assertOk()
        ->assertJsonPath('reset', false);

    expect($response->json('messages.*.id'))->not->toContain($old->id)
        ->toContain($arrived->id)
        ->and($response->json('cursor'))->toBeGreaterThan($cursor);
});

test('incremental polling perceives an ACK update of a message already shown', function () {
    $lead = pollingLead();
    $outbound = pollingMessage($lead, LeadActivity::DIRECTION_OUTBOUND, [
        'provider_message_id' => '3EB0POLLINGACK000001',
        'message_status' => LeadActivity::MESSAGE_STATUS_SERVER,
        'created_at' => now()->subMinutes(5),
    ]);
    $untouched = pollingMessage($lead, LeadActivity::DIRECTION_INBOUND, ['created_at' => now()->subMinutes(4)]);

    $cursor = $this->actingAs(User::factory()->create())->getJson(messagesUrl($lead))->json('cursor');

    $this->travel(5)->seconds();
    $this->patchJson('/api/whatsapp/messages/3EB0POLLINGACK000001/status', ['status' => 'READ'], pollingToken())
        ->assertSuccessful();

    $this->getJson(messagesUrl($lead, ['since' => $cursor]))
        ->assertOk()
        ->assertJsonPath('messages.*.id', [$outbound->id])
        ->assertJsonPath('messages.0.message_status', LeadActivity::MESSAGE_STATUS_READ);

    expect($untouched->refresh()->read_at)->toBeNull();
});

test('a massive change asks the screen to reload the latest page', function () {
    $lead = pollingLead();
    collect(range(1, LeadWhatsappConversationController::CHANGES_LIMIT + 1))
        ->each(fn () => pollingMessage($lead, LeadActivity::DIRECTION_INBOUND));

    $this->actingAs(User::factory()->create())
        ->getJson(messagesUrl($lead, ['since' => now()->subMinute()->getTimestamp()]))
        ->assertOk()
        ->assertJsonPath('messages', [])
        ->assertJsonPath('reset', true);
});

test('polling never marks messages as read by itself', function () {
    $lead = pollingLead();
    $inbound = pollingMessage($lead, LeadActivity::DIRECTION_INBOUND);

    $this->actingAs(User::factory()->create())
        ->getJson(messagesUrl($lead, ['since' => 0]))
        ->assertOk()
        ->assertJsonPath('messages.0.read_at', null);

    expect($inbound->refresh()->read_at)->toBeNull()
        ->and($lead->unreadWhatsappMessages()->count())->toBe(1);
});

test('reading marks only the pending inbound shown and the badge count drops', function () {
    $lead = pollingLead();
    $readBefore = pollingMessage($lead, LeadActivity::DIRECTION_INBOUND, ['read_at' => Carbon::parse('2026-09-20 08:00:00')]);
    $outbound = pollingMessage($lead, LeadActivity::DIRECTION_OUTBOUND);
    $shown = pollingMessage($lead, LeadActivity::DIRECTION_INBOUND);
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('leads.index'))->assertOk();
    expect(Lead::query()->withCount('unreadWhatsappMessages')->find($lead->id)->unread_whatsapp_messages_count)->toBe(1);

    $this->postJson(route('leads.whatsapp.read', $lead), ['up_to_id' => $shown->id])->assertOk()->assertExactJson(['marked' => 1]);

    // Chegou depois do que a tela exibiu: continua pendente.
    $later = pollingMessage($lead, LeadActivity::DIRECTION_INBOUND);

    expect($shown->refresh()->read_at)->not->toBeNull()
        ->and($readBefore->refresh()->read_at->toDateTimeString())->toBe('2026-09-20 08:00:00')
        ->and($outbound->refresh()->read_at)->toBeNull()
        ->and($outbound->message_status)->toBe(LeadActivity::MESSAGE_STATUS_SERVER)
        ->and($later->refresh()->read_at)->toBeNull()
        ->and(Lead::query()->withCount('unreadWhatsappMessages')->find($lead->id)->unread_whatsapp_messages_count)->toBe(1);
});

test('the read status appears in the next poll without being reported as unread', function () {
    $lead = pollingLead();
    $inbound = pollingMessage($lead, LeadActivity::DIRECTION_INBOUND, ['created_at' => now()->subMinute()]);
    $this->actingAs(User::factory()->create());

    $cursor = $this->getJson(messagesUrl($lead))->assertJsonPath('messages.0.read_at', null)->json('cursor');

    $this->travel(5)->seconds();
    $this->postJson(route('leads.whatsapp.read', $lead), ['up_to_id' => $inbound->id])->assertOk();

    $this->getJson(messagesUrl($lead, ['since' => $cursor]))
        ->assertJsonPath('messages.*.id', [$inbound->id])
        ->assertJsonPath('messages.0.read_at', fn ($readAt) => $readAt !== null);
});

test('the previous WhatsApp flows keep feeding the conversation through polling', function () {
    app(CompanyWhatsappSettings::class)->save([
        'enabled' => true,
        'number' => '5551998931325',
        'provider' => CompanyWhatsappSettings::PROVIDER_WAHA,
        'session' => 'vetoros1-1',
    ]);
    $lead = pollingLead(['whatsapp' => '5551995179173']);
    $user = User::factory()->create();
    $cursor = $this->actingAs($user)->getJson(messagesUrl($lead))->json('cursor');

    $this->travel(5)->seconds();

    // Envio pelo CRM registrado pelo n8n (/log), repetido: continua uma atividade só.
    $log = ['message_id' => '3EB0POLLINGLOG000001', 'status' => 'PENDING', 'message' => 'Enviada pelo CRM'];
    $this->postJson("/api/prospects/{$lead->id}/whatsapp/log", $log, pollingToken())->assertSuccessful();
    $this->postJson("/api/prospects/{$lead->id}/whatsapp/log", $log, pollingToken())->assertSuccessful();

    $this->travel(1)->seconds();
    $this->postJson('/api/whatsapp/inbound', [
        'session' => 'vetoros1-1',
        'provider_message_id' => '3EB0POLLINGINB000001',
        'from' => '555195179173',
        'message' => 'Resposta do cliente',
    ], pollingToken())->assertSuccessful()->assertJsonPath('success', true);

    $this->travel(1)->seconds();
    $this->postJson('/api/whatsapp/outbound-external', [
        'session' => 'vetoros1-1',
        'provider_message_id' => '3EB0POLLINGEXT000001',
        'to' => '555195179173@s.whatsapp.net',
        'message' => 'Enviada pelo celular',
        'status' => 'SERVER',
    ], pollingToken())->assertSuccessful()->assertJsonPath('success', true);

    $this->getJson(messagesUrl($lead, ['since' => $cursor]))
        ->assertOk()
        ->assertJsonPath('messages.*.description', ['Enviada pelo CRM', 'Resposta do cliente', 'Enviada pelo celular'])
        ->assertJsonPath('messages.*.direction', ['outbound', 'inbound', 'outbound'])
        ->assertJsonPath('messages.*.message_status', ['PENDING', null, 'SERVER'])
        ->assertJsonPath('messages.1.read_at', null);
});
