<?php

use App\Http\Controllers\LeadController;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\User;
use App\Services\CompanyWhatsappSettings;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

function awaitingReplyLead(array $overrides = []): Lead
{
    return Lead::query()->create(array_merge([
        'company_name' => 'Lead aguardando',
        'product' => 'site',
        'status' => 'contacted',
        'whatsapp' => '5551995179173',
    ], $overrides));
}

/** Mensagem da conversa gravada no instante informado (created_at define a ordem da conversa). */
function awaitingReplyMessage(Lead $lead, string $direction, string $at, array $overrides = []): LeadActivity
{
    $activity = $lead->activities()->create(array_merge([
        'type' => 'whatsapp',
        'direction' => $direction,
        'contacted_at' => $at,
        'description' => $direction === LeadActivity::DIRECTION_INBOUND ? 'Mensagem do cliente.' : 'Mensagem da empresa.',
        'provider_message_id' => 'msg_'.fake()->unique()->uuid(),
        'message_status' => $direction === LeadActivity::DIRECTION_OUTBOUND ? LeadActivity::MESSAGE_STATUS_SERVER : null,
    ], $overrides));

    $activity->forceFill(['created_at' => $at, 'updated_at' => $at])->save();

    return $activity->refresh();
}

/** @return list<int> */
function awaitingReplyIds(): array
{
    return Lead::query()->whatsappReplyQueue()->pluck('id')->all();
}

function awaitingReplyToken(): array
{
    return ['Authorization' => 'Bearer '.config('services.ab_prospect.token')];
}

beforeEach(function () {
    Carbon::setTestNow('2026-09-27 15:00:00');
});

test('the awaiting reply queue requires an admin user', function () {
    $this->get(route('leads.awaiting-reply'))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->reader()->create())
        ->get(route('leads.awaiting-reply'))
        ->assertForbidden();
});

test('a lead without WhatsApp messages is not awaiting a reply', function () {
    $lead = awaitingReplyLead();
    $lead->activities()->create(['type' => 'note', 'description' => 'Nota.']);
    // WhatsApp registrado à mão em "Registrar contato": sem direction, não é conversa.
    $lead->activities()->create(['type' => 'whatsapp', 'description' => 'Falei com o cliente.']);

    expect(awaitingReplyIds())->toBe([])
        ->and(Lead::query()->awaitingWhatsappReply()->count())->toBe(0);
});

test('a lead with only outbound messages is not awaiting a reply', function () {
    $lead = awaitingReplyLead();
    awaitingReplyMessage($lead, LeadActivity::DIRECTION_OUTBOUND, '2026-09-27 10:00:00');
    awaitingReplyMessage($lead, LeadActivity::DIRECTION_OUTBOUND, '2026-09-27 11:00:00');

    expect(awaitingReplyIds())->toBe([]);
});

test('an inbound message puts the lead in the queue', function () {
    $lead = awaitingReplyLead();
    awaitingReplyMessage($lead, LeadActivity::DIRECTION_INBOUND, '2026-09-27 14:52:00', ['description' => 'Gostaria de saber o valor.']);

    $queued = Lead::query()->whatsappReplyQueue()->sole();

    expect($queued->id)->toBe($lead->id)
        ->and($queued->awaiting_reply_since->toDateTimeString())->toBe('2026-09-27 14:52:00')
        ->and($queued->last_inbound_at->toDateTimeString())->toBe('2026-09-27 14:52:00')
        ->and($queued->last_inbound_message)->toBe('Gostaria de saber o valor.');
});

test('consecutive inbound messages keep a single queue item per lead', function () {
    $lead = awaitingReplyLead();
    awaitingReplyMessage($lead, LeadActivity::DIRECTION_INBOUND, '2026-09-27 14:00:00', ['description' => 'Olá']);
    awaitingReplyMessage($lead, LeadActivity::DIRECTION_INBOUND, '2026-09-27 14:05:00', ['description' => 'Gostaria de informações']);
    awaitingReplyMessage($lead, LeadActivity::DIRECTION_INBOUND, '2026-09-27 14:10:00', ['description' => 'Qual o valor?']);

    $queue = Lead::query()->whatsappReplyQueue()->get();

    expect($queue)->toHaveCount(1)
        ->and(Lead::query()->awaitingWhatsappReply()->count())->toBe(1)
        // A espera começa na primeira mensagem sem resposta; o trecho é o da última.
        ->and($queue->first()->awaiting_reply_since->toDateTimeString())->toBe('2026-09-27 14:00:00')
        ->and($queue->first()->last_inbound_at->toDateTimeString())->toBe('2026-09-27 14:10:00')
        ->and($queue->first()->last_inbound_message)->toBe('Qual o valor?');
});

test('reading the inbound message does not remove the lead from the queue', function () {
    $lead = awaitingReplyLead();
    $inbound = awaitingReplyMessage($lead, LeadActivity::DIRECTION_INBOUND, '2026-09-27 14:00:00');
    $this->actingAs(User::factory()->create());

    // Abrir o lead e a conversa marcam como lida.
    $this->get(route('leads.edit', $lead))->assertOk();
    $this->postJson(route('leads.whatsapp.read', $lead), ['up_to_id' => $inbound->id])->assertOk();

    expect($inbound->refresh()->read_at)->not->toBeNull()
        ->and($lead->unreadWhatsappMessages()->count())->toBe(0)
        ->and(awaitingReplyIds())->toBe([$lead->id]);
});

test('a later outbound message removes the lead from the queue', function () {
    $lead = awaitingReplyLead();
    awaitingReplyMessage($lead, LeadActivity::DIRECTION_INBOUND, '2026-09-27 14:00:00');
    awaitingReplyMessage($lead, LeadActivity::DIRECTION_OUTBOUND, '2026-09-27 14:03:00');

    expect(awaitingReplyIds())->toBe([]);
});

test('a new inbound after the reply puts the lead back in the queue', function () {
    $lead = awaitingReplyLead();
    awaitingReplyMessage($lead, LeadActivity::DIRECTION_INBOUND, '2026-09-27 13:00:00');
    awaitingReplyMessage($lead, LeadActivity::DIRECTION_OUTBOUND, '2026-09-27 13:05:00');
    awaitingReplyMessage($lead, LeadActivity::DIRECTION_INBOUND, '2026-09-27 14:30:00', ['description' => 'Qual o valor?']);

    $queued = Lead::query()->whatsappReplyQueue()->sole();

    // A espera recomeça na inbound posterior à resposta, não na primeira da conversa.
    expect($queued->id)->toBe($lead->id)
        ->and($queued->awaiting_reply_since->toDateTimeString())->toBe('2026-09-27 14:30:00')
        ->and($queued->last_inbound_message)->toBe('Qual o valor?');
});

test('the order follows created_at and id, like the conversation', function () {
    $answered = awaitingReplyLead(['company_name' => 'Respondido no mesmo segundo']);
    awaitingReplyMessage($answered, LeadActivity::DIRECTION_INBOUND, '2026-09-27 14:00:00');
    awaitingReplyMessage($answered, LeadActivity::DIRECTION_OUTBOUND, '2026-09-27 14:00:00');

    $waiting = awaitingReplyLead(['company_name' => 'Cliente escreveu no mesmo segundo', 'whatsapp' => '5551988887777']);
    awaitingReplyMessage($waiting, LeadActivity::DIRECTION_OUTBOUND, '2026-09-27 14:00:00');
    awaitingReplyMessage($waiting, LeadActivity::DIRECTION_INBOUND, '2026-09-27 14:00:00');

    // Uma outbound mais antiga, mas com id maior, não responde uma inbound mais recente.
    $late = awaitingReplyLead(['company_name' => 'Outbound com id maior e data anterior', 'whatsapp' => '5551977776666']);
    awaitingReplyMessage($late, LeadActivity::DIRECTION_INBOUND, '2026-09-27 14:10:00');
    awaitingReplyMessage($late, LeadActivity::DIRECTION_OUTBOUND, '2026-09-27 14:05:00');

    expect(awaitingReplyIds())->toBe([$waiting->id, $late->id]);
});

test('an external outbound registered by n8n removes the lead from the queue', function () {
    app(CompanyWhatsappSettings::class)->save([
        'enabled' => true,
        'number' => '5551998931325',
        'provider' => CompanyWhatsappSettings::PROVIDER_WAHA,
        'session' => 'vetoros1-1',
    ]);
    $lead = awaitingReplyLead();

    $this->postJson('/api/whatsapp/inbound', [
        'session' => 'vetoros1-1',
        'provider_message_id' => 'IN-1',
        'from' => '555195179173',
        'message' => 'Pode me ligar amanhã?',
    ], awaitingReplyToken())->assertCreated();

    expect(awaitingReplyIds())->toBe([$lead->id]);

    Carbon::setTestNow('2026-09-27 15:02:00');

    $this->postJson('/api/whatsapp/outbound-external', [
        'session' => 'vetoros1-1',
        'provider_message_id' => 'OUT-EXT-1',
        'to' => '555195179173@s.whatsapp.net',
        'message' => 'Posso sim, às 10h.',
        'status' => 'SERVER',
    ], awaitingReplyToken())->assertCreated();

    expect(awaitingReplyIds())->toBe([]);
});

test('an outbound logged by the CRM send flow removes the lead from the queue', function () {
    $lead = awaitingReplyLead();

    $this->postJson('/api/whatsapp/inbound', [
        'session' => 'vetoros1-1',
        'provider_message_id' => 'IN-2',
        'from' => '555195179173',
        'message' => 'Olá',
    ], awaitingReplyToken())->assertCreated();

    Carbon::setTestNow('2026-09-27 15:01:00');

    $this->postJson("/api/prospects/{$lead->id}/whatsapp/log", [
        'message_id' => 'OUT-CRM-1',
        'status' => 'PENDING',
        'message' => 'Olá, como posso ajudar?',
    ], awaitingReplyToken())->assertCreated();

    expect(awaitingReplyIds())->toBe([]);
});

test('ACK changes never affect the queue', function (string $status) {
    $lead = awaitingReplyLead();
    awaitingReplyMessage($lead, LeadActivity::DIRECTION_INBOUND, '2026-09-27 13:00:00');
    $outbound = awaitingReplyMessage($lead, LeadActivity::DIRECTION_OUTBOUND, '2026-09-27 13:05:00', [
        'provider_message_id' => 'ACK-1',
        'message_status' => LeadActivity::MESSAGE_STATUS_PENDING,
    ]);
    $inbound = awaitingReplyMessage($lead, LeadActivity::DIRECTION_INBOUND, '2026-09-27 14:00:00');

    expect(awaitingReplyIds())->toBe([$lead->id]);

    // O ACK chega depois da nova inbound e toca o updated_at da outbound: nada muda.
    $this->patchJson('/api/whatsapp/messages/ACK-1/status', ['status' => $status], awaitingReplyToken())->assertOk();

    expect($outbound->refresh()->message_status)->toBe($status)
        ->and(awaitingReplyIds())->toBe([$lead->id]);

    // E uma outbound só com ACK técnico (sem leitura do cliente) já conta como resposta.
    $inbound->delete();

    expect(awaitingReplyIds())->toBe([]);
})->with(['SERVER', 'DEVICE', 'READ']);

test('notes, calls, e-mails and meetings after the inbound do not answer it', function () {
    $lead = awaitingReplyLead();
    awaitingReplyMessage($lead, LeadActivity::DIRECTION_INBOUND, '2026-09-27 14:00:00');
    $this->actingAs(User::factory()->create());

    foreach (['note', 'call', 'email', 'meeting', 'whatsapp'] as $type) {
        $this->post(route('leads.activities.store', $lead), [
            'type' => $type,
            'description' => "Registro {$type}.",
            'contacted_at' => '2026-09-27T14:30',
        ])->assertSessionHasNoErrors();
    }

    // Mudar o próximo contato também grava uma nota automática no histórico.
    $lead->refresh()->update(['next_follow_up_at' => '2026-09-28 10:00']);

    expect($lead->activities()->count())->toBeGreaterThanOrEqual(7)
        ->and(awaitingReplyIds())->toBe([$lead->id]);
});

test('a lead without an owner is listed with no owner', function () {
    $lead = awaitingReplyLead(['user_id' => null]);
    awaitingReplyMessage($lead, LeadActivity::DIRECTION_INBOUND, '2026-09-27 14:00:00');

    $this->actingAs(User::factory()->create())
        ->get(route('leads.awaiting-reply'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('leads/awaiting-reply')
            ->has('leads.data', 1)
            ->where('leads.data.0.id', $lead->id)
            ->where('leads.data.0.user_id', null)
            ->where('leads.data.0.user', null));
});

test('converted and lost leads with a new inbound stay in the queue', function (string $status) {
    $lead = awaitingReplyLead([
        'status' => $status,
        'lost_reason' => $status === 'lost' ? 'price' : null,
    ]);
    awaitingReplyMessage($lead, LeadActivity::DIRECTION_OUTBOUND, '2026-09-20 10:00:00');
    awaitingReplyMessage($lead, LeadActivity::DIRECTION_INBOUND, '2026-09-27 14:00:00');

    expect(awaitingReplyIds())->toBe([$lead->id]);

    $this->actingAs(User::factory()->create())
        ->get(route('leads.awaiting-reply', ['status' => $status]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('leads.data', 1)
            ->where('leads.data.0.status', $status));
})->with(['converted', 'lost']);

test('the queue lists the longest waiting lead first', function () {
    $recent = awaitingReplyLead(['company_name' => 'Recente', 'whatsapp' => '5551911111111']);
    awaitingReplyMessage($recent, LeadActivity::DIRECTION_INBOUND, '2026-09-27 14:50:00');

    $oldest = awaitingReplyLead(['company_name' => 'Mais antigo', 'whatsapp' => '5551922222222']);
    awaitingReplyMessage($oldest, LeadActivity::DIRECTION_INBOUND, '2026-09-26 09:00:00');
    awaitingReplyMessage($oldest, LeadActivity::DIRECTION_INBOUND, '2026-09-27 14:59:00');

    $middle = awaitingReplyLead(['company_name' => 'Intermediário', 'whatsapp' => '5551933333333']);
    awaitingReplyMessage($middle, LeadActivity::DIRECTION_INBOUND, '2026-09-27 08:00:00');

    expect(awaitingReplyIds())->toBe([$oldest->id, $middle->id, $recent->id]);

    $this->actingAs(User::factory()->create())
        ->get(route('leads.awaiting-reply'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('leads.total', 3)
            ->where('leads.data.0.id', $oldest->id)
            ->where('leads.data.1.id', $middle->id)
            ->where('leads.data.2.id', $recent->id));
});

test('the counter matches the leads actually awaiting a reply', function () {
    $waitingA = awaitingReplyLead(['whatsapp' => '5551911111111']);
    awaitingReplyMessage($waitingA, LeadActivity::DIRECTION_INBOUND, '2026-09-27 14:00:00');
    awaitingReplyMessage($waitingA, LeadActivity::DIRECTION_INBOUND, '2026-09-27 14:01:00');

    $waitingB = awaitingReplyLead(['whatsapp' => '5551922222222', 'status' => 'lost', 'lost_reason' => 'price']);
    awaitingReplyMessage($waitingB, LeadActivity::DIRECTION_INBOUND, '2026-09-27 14:00:00');

    $answered = awaitingReplyLead(['whatsapp' => '5551933333333']);
    awaitingReplyMessage($answered, LeadActivity::DIRECTION_INBOUND, '2026-09-27 14:00:00');
    awaitingReplyMessage($answered, LeadActivity::DIRECTION_OUTBOUND, '2026-09-27 14:02:00');

    awaitingReplyLead(['whatsapp' => '5551944444444']);

    $deleted = awaitingReplyLead(['whatsapp' => '5551955555555']);
    awaitingReplyMessage($deleted, LeadActivity::DIRECTION_INBOUND, '2026-09-27 14:00:00');
    $deleted->delete();

    $this->actingAs(User::factory()->create());

    $this->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('metrics.awaiting_reply', 2));

    $this->get(route('leads.index'))
        ->assertInertia(fn (Assert $page) => $page->where('awaitingReplyTotal', 2));

    $this->get(route('leads.awaiting-reply'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('leads.total', 2)
            ->has('leads.data', 2));
});

test('unread and awaiting reply are independent', function () {
    // Não lida e aguardando.
    $unreadWaiting = awaitingReplyLead(['whatsapp' => '5551911111111']);
    awaitingReplyMessage($unreadWaiting, LeadActivity::DIRECTION_INBOUND, '2026-09-27 14:00:00');

    // Lida e aguardando.
    $readWaiting = awaitingReplyLead(['whatsapp' => '5551922222222']);
    awaitingReplyMessage($readWaiting, LeadActivity::DIRECTION_INBOUND, '2026-09-27 14:00:00', ['read_at' => '2026-09-27 14:01:00']);

    // Não lida e respondida (respondida pelo celular sem abrir o CRM).
    $unreadAnswered = awaitingReplyLead(['whatsapp' => '5551933333333']);
    awaitingReplyMessage($unreadAnswered, LeadActivity::DIRECTION_INBOUND, '2026-09-27 14:00:00');
    awaitingReplyMessage($unreadAnswered, LeadActivity::DIRECTION_OUTBOUND, '2026-09-27 14:05:00');

    expect(awaitingReplyIds())->toEqualCanonicalizing([$unreadWaiting->id, $readWaiting->id])
        ->and($unreadAnswered->unreadWhatsappMessages()->count())->toBe(1)
        ->and($readWaiting->unreadWhatsappMessages()->count())->toBe(0);

    // Abrir a fila não marca nada como lido.
    $this->actingAs(User::factory()->create())
        ->get(route('leads.awaiting-reply'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('leads.data.0.unread_whatsapp_messages_count', 1)
            ->where('leads.data.1.unread_whatsapp_messages_count', 0));

    expect(LeadActivity::query()->unreadInbound()->count())->toBe(2);
});

test('the queue exposes the item fields and a trimmed excerpt of the last inbound', function () {
    $owner = User::factory()->create(['name' => 'Sabrina']);
    $lead = awaitingReplyLead(['company_name' => 'Empresa XYZ', 'contact_name' => 'Ana', 'user_id' => $owner->id]);
    awaitingReplyMessage($lead, LeadActivity::DIRECTION_INBOUND, '2026-09-27 14:23:00', [
        'description' => "Bom dia!\n\n".str_repeat('texto longo ', 40),
    ]);

    $this->actingAs($owner)
        ->get(route('leads.awaiting-reply'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('leads.data.0.company_name', 'Empresa XYZ')
            ->where('leads.data.0.contact_name', 'Ana')
            ->where('leads.data.0.whatsapp', '5551995179173')
            ->where('leads.data.0.user.name', 'Sabrina')
            ->where('leads.data.0.awaiting_reply_since', '2026-09-27T14:23:00.000000Z')
            ->where('leads.data.0.last_inbound_at', '2026-09-27T14:23:00.000000Z')
            ->where('leads.data.0.last_inbound_message', fn (string $excerpt) => str_starts_with($excerpt, 'Bom dia! texto longo')
                && mb_strlen($excerpt) <= LeadController::AWAITING_REPLY_EXCERPT_LENGTH + 3)
            ->missing('leads.data.0.activities'));
});

test('the queue applies the owner, status, product and search filters', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $mine = awaitingReplyLead(['company_name' => 'Minha empresa', 'user_id' => $user->id, 'whatsapp' => '5551911111111']);
    awaitingReplyMessage($mine, LeadActivity::DIRECTION_INBOUND, '2026-09-27 14:00:00');

    $theirs = awaitingReplyLead(['company_name' => 'Outra empresa', 'user_id' => $other->id, 'product' => 'vetoros', 'status' => 'interested', 'whatsapp' => '5551922222222']);
    awaitingReplyMessage($theirs, LeadActivity::DIRECTION_INBOUND, '2026-09-27 14:00:00');

    $this->actingAs($user);

    $ids = fn (array $query) => collect($this->get(route('leads.awaiting-reply', $query))->viewData('page')['props']['leads']['data'])->pluck('id')->all();

    expect($ids([]))->toEqualCanonicalizing([$mine->id, $theirs->id])
        ->and($ids(['owner' => 'mine']))->toBe([$mine->id])
        ->and($ids(['owner' => $other->id]))->toBe([$theirs->id])
        ->and($ids(['status' => 'interested']))->toBe([$theirs->id])
        ->and($ids(['product' => 'vetoros']))->toBe([$theirs->id])
        ->and($ids(['search' => 'Minha']))->toBe([$mine->id]);
});

test('the queue paginates without duplicating leads', function () {
    foreach (range(1, LeadController::AWAITING_REPLY_PER_PAGE + 5) as $i) {
        $lead = awaitingReplyLead(['company_name' => "Lead {$i}", 'whatsapp' => '55519'.str_pad((string) $i, 8, '0', STR_PAD_LEFT)]);
        awaitingReplyMessage($lead, LeadActivity::DIRECTION_INBOUND, Carbon::parse('2026-09-27 08:00:00')->addMinutes($i)->toDateTimeString());
        awaitingReplyMessage($lead, LeadActivity::DIRECTION_INBOUND, Carbon::parse('2026-09-27 09:00:00')->addMinutes($i)->toDateTimeString());
    }

    $this->actingAs(User::factory()->create());

    $page = fn (int $number) => $this->get(route('leads.awaiting-reply', ['page' => $number]))->viewData('page')['props']['leads'];

    $first = $page(1);
    $second = $page(2);
    $ids = collect([...$first['data'], ...$second['data']])->pluck('id');

    expect($first['total'])->toBe(LeadController::AWAITING_REPLY_PER_PAGE + 5)
        ->and($first['data'])->toHaveCount(LeadController::AWAITING_REPLY_PER_PAGE)
        ->and($second['data'])->toHaveCount(5)
        ->and($ids->unique())->toHaveCount(LeadController::AWAITING_REPLY_PER_PAGE + 5)
        ->and($first['data'][0]['company_name'])->toBe('Lead 1');
});

test('the conversation and the history keep working for a lead in the queue', function () {
    $lead = awaitingReplyLead();
    $outbound = awaitingReplyMessage($lead, LeadActivity::DIRECTION_OUTBOUND, '2026-09-27 13:00:00');
    $inbound = awaitingReplyMessage($lead, LeadActivity::DIRECTION_INBOUND, '2026-09-27 14:00:00');
    $this->actingAs(User::factory()->create());

    $this->getJson(route('leads.whatsapp.messages', $lead))
        ->assertOk()
        ->assertJsonPath('messages.0.id', $outbound->id)
        ->assertJsonPath('messages.1.id', $inbound->id);

    $this->get(route('leads.awaiting-reply'))->assertOk();

    $this->get(route('leads.edit', $lead))
        ->assertInertia(fn (Assert $page) => $page
            ->component('leads/edit')
            ->has('lead.activities', 2));

    // A fila é só leitura: nenhuma atividade criada ou alterada.
    expect($lead->activities()->count())->toBe(2)
        ->and($outbound->refresh()->message_status)->toBe(LeadActivity::MESSAGE_STATUS_SERVER)
        ->and(awaitingReplyIds())->toBe([$lead->id]);
});
