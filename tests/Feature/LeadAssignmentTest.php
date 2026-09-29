<?php

use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\User;
use App\Services\CompanyWhatsappSettings;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

function leadAssignmentLead(array $overrides = []): Lead
{
    return Lead::query()->create(array_merge([
        'company_name' => 'Lead para atendimento',
        'product' => 'site',
        'status' => 'contacted',
        'whatsapp' => '5551995179173',
    ], $overrides));
}

/** Mensagem da conversa gravada no instante informado (created_at define a ordem da conversa). */
function leadAssignmentMessage(Lead $lead, string $direction, string $at): LeadActivity
{
    $activity = $lead->activities()->create([
        'type' => 'whatsapp',
        'direction' => $direction,
        'contacted_at' => $at,
        'description' => $direction === LeadActivity::DIRECTION_INBOUND ? 'Mensagem do cliente.' : 'Mensagem da empresa.',
        'provider_message_id' => 'msg_'.fake()->unique()->uuid(),
        'message_status' => $direction === LeadActivity::DIRECTION_OUTBOUND ? LeadActivity::MESSAGE_STATUS_SERVER : null,
    ]);

    $activity->forceFill(['created_at' => $at, 'updated_at' => $at])->save();

    return $activity->refresh();
}

/** Lead com uma inbound não lida às 14:00: está na fila e tem 1 não lida. */
function leadAssignmentWaitingLead(array $overrides = []): Lead
{
    $lead = leadAssignmentLead($overrides);
    leadAssignmentMessage($lead, LeadActivity::DIRECTION_INBOUND, '2026-09-28 14:00:00');

    return $lead;
}

/** @return list<int> */
function leadAssignmentQueueIds(): array
{
    return Lead::query()->whatsappReplyQueue()->pluck('id')->all();
}

function leadAssignmentToken(): array
{
    return ['Authorization' => 'Bearer '.config('services.ab_prospect.token')];
}

beforeEach(function () {
    Carbon::setTestNow('2026-09-28 15:00:00');
});

test('an unassigned lead can be taken by the authenticated admin', function () {
    $user = User::factory()->create(['name' => 'João Silva']);
    $lead = leadAssignmentWaitingLead();

    $this->actingAs($user)
        ->from(route('leads.awaiting-reply'))
        ->patch(route('leads.assign-to-me', $lead))
        ->assertRedirect(route('leads.awaiting-reply'))
        ->assertInertiaFlash('toast.type', 'success');

    expect($lead->refresh()->user_id)->toBe($user->id);
});

test('taking a lead records exactly one history note without direction', function () {
    $user = User::factory()->create(['name' => 'João Silva']);
    $lead = leadAssignmentWaitingLead();

    $this->actingAs($user)->patch(route('leads.assign-to-me', $lead))->assertRedirect();

    $notes = $lead->activities()->where('type', 'note')->get();

    expect($notes)->toHaveCount(1)
        ->and($notes->first()->direction)->toBeNull()
        ->and($notes->first()->user_id)->toBe($user->id)
        ->and($notes->first()->description)->toBe('Atendimento assumido por João Silva.')
        ->and($lead->activities()->count())->toBe(2);
});

test('taking a lead keeps messages unread, queued and untouched', function () {
    $user = User::factory()->create();
    $lead = leadAssignmentWaitingLead(['next_follow_up_at' => '2026-10-01 10:00', 'status' => 'interested']);
    $inbound = $lead->activities()->where('type', 'whatsapp')->sole();
    $messagesBefore = $lead->activities()->where('type', 'whatsapp')->get()->toArray();
    $followUpBefore = $lead->refresh()->next_follow_up_at->toIso8601String();

    $this->actingAs($user)->patch(route('leads.assign-to-me', $lead))->assertRedirect();

    $lead->refresh();

    expect($inbound->refresh()->read_at)->toBeNull()
        ->and($lead->unreadWhatsappMessages()->count())->toBe(1)
        ->and(leadAssignmentQueueIds())->toBe([$lead->id])
        ->and($lead->activities()->where('type', 'whatsapp')->get()->toArray())->toBe($messagesBefore)
        ->and($lead->activities()->where('direction', LeadActivity::DIRECTION_OUTBOUND)->exists())->toBeFalse()
        ->and($lead->status)->toBe('interested')
        ->and($lead->next_follow_up_at->toIso8601String())->toBe($followUpBefore);
});

test('the full flow: take, read, reply and a new inbound brings the lead back to its owner', function () {
    app(CompanyWhatsappSettings::class)->save([
        'enabled' => true,
        'number' => '5551998931325',
        'provider' => CompanyWhatsappSettings::PROVIDER_WAHA,
        'session' => 'vetoros1-1',
    ]);
    $user = User::factory()->create();
    $lead = leadAssignmentLead();

    $this->postJson('/api/whatsapp/inbound', [
        'session' => 'vetoros1-1',
        'provider_message_id' => 'IN-A1',
        'from' => '555195179173',
        'message' => 'Olá, quero um site.',
    ], leadAssignmentToken())->assertCreated();

    expect(leadAssignmentQueueIds())->toBe([$lead->id]);

    $this->actingAs($user)->patch(route('leads.assign-to-me', $lead))->assertRedirect();

    expect(leadAssignmentQueueIds())->toBe([$lead->id])
        ->and($lead->unreadWhatsappMessages()->count())->toBe(1);

    // Abrir a conversa marca como lida, mas o lead continua aguardando resposta.
    $this->get(route('leads.edit', $lead))->assertOk();

    expect($lead->unreadWhatsappMessages()->count())->toBe(0)
        ->and(leadAssignmentQueueIds())->toBe([$lead->id]);

    Carbon::setTestNow('2026-09-28 15:02:00');

    $this->postJson("/api/prospects/{$lead->id}/whatsapp/log", [
        'message_id' => 'OUT-A1',
        'status' => 'PENDING',
        'message' => 'Olá! Vamos conversar.',
    ], leadAssignmentToken())->assertCreated();

    expect(leadAssignmentQueueIds())->toBe([]);

    Carbon::setTestNow('2026-09-28 15:10:00');

    $this->postJson('/api/whatsapp/inbound', [
        'session' => 'vetoros1-1',
        'provider_message_id' => 'IN-A2',
        'from' => '555195179173',
        'message' => 'Qual o valor?',
    ], leadAssignmentToken())->assertCreated();

    expect(leadAssignmentQueueIds())->toBe([$lead->id])
        ->and($lead->refresh()->user_id)->toBe($user->id);
});

test('owner=mine returns the lead after it is taken', function () {
    $user = User::factory()->create();
    $lead = leadAssignmentWaitingLead();

    $this->actingAs($user)
        ->get(route('leads.awaiting-reply', ['owner' => 'mine']))
        ->assertInertia(fn (Assert $page) => $page->has('leads.data', 0));

    $this->patch(route('leads.assign-to-me', $lead))->assertRedirect();

    $this->get(route('leads.awaiting-reply', ['owner' => 'mine']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('leads.data', 1)
            ->where('leads.data.0.id', $lead->id));

    $this->get(route('leads.index', ['owner' => 'mine']))
        ->assertInertia(fn (Assert $page) => $page->where('leads.total', 1));
});

test('assign-to-me never takes a lead that already has another owner', function () {
    $owner = User::factory()->create(['name' => 'Maria Souza']);
    $intruder = User::factory()->create();
    $lead = leadAssignmentWaitingLead(['user_id' => $owner->id]);

    $this->actingAs($intruder)
        ->patch(route('leads.assign-to-me', $lead), ['user_id' => $intruder->id])
        ->assertRedirect()
        ->assertInertiaFlash('toast.type', 'error')
        ->assertInertiaFlash('toast.message', 'Este lead já foi assumido por Maria Souza.');

    expect($lead->refresh()->user_id)->toBe($owner->id)
        ->and($lead->activities()->where('type', 'note')->count())->toBe(0);
});

test('assign-to-me ignores a user_id sent by the client', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $lead = leadAssignmentWaitingLead();

    $this->actingAs($user)
        ->patch(route('leads.assign-to-me', $lead), ['user_id' => $other->id])
        ->assertRedirect();

    expect($lead->refresh()->user_id)->toBe($user->id);
});

test('taking a lead that is already yours changes nothing', function () {
    $user = User::factory()->create();
    $lead = leadAssignmentWaitingLead(['user_id' => $user->id]);

    $this->actingAs($user)
        ->patch(route('leads.assign-to-me', $lead))
        ->assertRedirect()
        ->assertInertiaFlash('toast.message', 'Este atendimento já é seu.');

    expect($lead->activities()->where('type', 'note')->count())->toBe(0);
});

test('the first assignment wins when two operators take the same lead', function () {
    $first = User::factory()->create(['name' => 'Operador A']);
    $second = User::factory()->create(['name' => 'Operador B']);
    $lead = leadAssignmentWaitingLead();

    // Operador B carregou o lead antes do clique de A: a cópia em memória ainda está sem responsável.
    $staleForSecond = Lead::query()->findOrFail($lead->id);

    expect($lead->assignTo($first))->toBeTrue()
        ->and($staleForSecond->user_id)->toBeNull()
        ->and($staleForSecond->assignTo($second))->toBeFalse()
        ->and($lead->refresh()->user_id)->toBe($first->id)
        ->and($lead->activities()->where('type', 'note')->pluck('description')->all())
        ->toBe(['Atendimento assumido por Operador A.']);

    $this->actingAs($second)
        ->patch(route('leads.assign-to-me', $lead))
        ->assertInertiaFlash('toast.message', 'Este lead já foi assumido por Operador A.');

    expect($lead->refresh()->user_id)->toBe($first->id);
});

test('an explicit transfer from A to B works and is recorded in the history', function () {
    $actor = User::factory()->create(['name' => 'Supervisor']);
    $from = User::factory()->create(['name' => 'João Silva']);
    $to = User::factory()->create(['name' => 'Maria Souza']);
    $lead = leadAssignmentWaitingLead(['user_id' => $from->id, 'next_follow_up_at' => '2026-10-01 10:00']);
    $inbound = $lead->activities()->where('type', 'whatsapp')->sole();
    $followUpBefore = $lead->refresh()->next_follow_up_at->toIso8601String();

    $this->actingAs($actor)
        ->from(route('leads.awaiting-reply'))
        ->patch(route('leads.bulk'), ['ids' => [$lead->id], 'user_id' => $to->id])
        ->assertRedirect(route('leads.awaiting-reply'));

    $lead->refresh();
    $note = $lead->activities()->where('type', 'note')->sole();

    expect($lead->user_id)->toBe($to->id)
        ->and($note->description)->toBe('Responsável alterado de João Silva para Maria Souza.')
        ->and($note->direction)->toBeNull()
        ->and($note->user_id)->toBe($actor->id)
        // Transferir não mexe na fila, nas não lidas, no status nem no próximo contato.
        ->and(leadAssignmentQueueIds())->toBe([$lead->id])
        ->and($inbound->refresh()->read_at)->toBeNull()
        ->and($lead->status)->toBe('contacted')
        ->and($lead->next_follow_up_at->toIso8601String())->toBe($followUpBefore);
});

test('defining and removing the owner through the existing screens are recorded too', function () {
    $user = User::factory()->create(['name' => 'João Silva']);
    $lead = leadAssignmentLead();

    $this->actingAs($user)->patch(route('leads.bulk'), ['ids' => [$lead->id], 'user_id' => $user->id]);
    $this->patch(route('leads.bulk'), ['ids' => [$lead->id], 'user_id' => $user->id]);
    $this->patch(route('leads.bulk'), ['ids' => [$lead->id], 'user_id' => null]);

    expect($lead->activities()->where('type', 'note')->orderBy('id')->pluck('description')->all())->toBe([
        'Responsável definido como João Silva.',
        'Responsável removido (era João Silva).',
    ]);
});

test('a transfer to a missing user is rejected', function () {
    $user = User::factory()->create();
    $lead = leadAssignmentWaitingLead(['user_id' => $user->id]);

    $this->actingAs($user)
        ->patch(route('leads.bulk'), ['ids' => [$lead->id], 'user_id' => 999999])
        ->assertSessionHasErrors('user_id');

    expect($lead->refresh()->user_id)->toBe($user->id);
});

test('converted and lost leads awaiting a reply can be taken without reopening them', function (string $status) {
    $user = User::factory()->create();
    $lead = leadAssignmentWaitingLead(['status' => $status, 'lost_reason' => $status === 'lost' ? 'price' : null]);

    $this->actingAs($user)->patch(route('leads.assign-to-me', $lead))->assertRedirect();

    $lead->refresh();

    expect($lead->user_id)->toBe($user->id)
        ->and($lead->status)->toBe($status)
        ->and(leadAssignmentQueueIds())->toBe([$lead->id]);
})->with(['converted', 'lost']);

test('the queue exposes the owner and the authenticated user for the owner label', function () {
    $user = User::factory()->create(['name' => 'João Silva']);
    $other = User::factory()->create(['name' => 'Maria Souza']);
    $unassigned = leadAssignmentWaitingLead(['company_name' => 'Sem responsável']);
    $mine = leadAssignmentWaitingLead(['company_name' => 'Meu', 'whatsapp' => '5551988887777', 'user_id' => $user->id]);
    $theirs = leadAssignmentWaitingLead(['company_name' => 'De outro', 'whatsapp' => '5551977776666', 'user_id' => $other->id]);

    $this->actingAs($user)
        ->get(route('leads.awaiting-reply'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('leads/awaiting-reply')
            ->where('auth.user.id', $user->id)
            ->has('leads.data', 3)
            ->where('leads.data.0.id', $unassigned->id)
            ->where('leads.data.0.user', null)
            ->where('leads.data.1.id', $mine->id)
            ->where('leads.data.1.user.id', $user->id)
            ->where('leads.data.2.id', $theirs->id)
            ->where('leads.data.2.user.name', 'Maria Souza')
            ->has('users', 2));
});

test('the assignment routes require an authenticated admin', function () {
    $lead = leadAssignmentWaitingLead();

    $this->patch(route('leads.assign-to-me', $lead))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->reader()->create())
        ->patch(route('leads.assign-to-me', $lead))
        ->assertForbidden();

    $this->patch(route('leads.bulk'), ['ids' => [$lead->id], 'user_id' => auth()->id()])
        ->assertForbidden();

    expect($lead->refresh()->user_id)->toBeNull()
        ->and($lead->activities()->where('type', 'note')->count())->toBe(0);
});

test('taking a missing or deleted lead returns not found', function () {
    $user = User::factory()->create();
    $deleted = leadAssignmentWaitingLead();
    $deleted->delete();

    $this->actingAs($user)->patch(route('leads.assign-to-me', 999999))->assertNotFound();
    $this->patch(route('leads.assign-to-me', $deleted))->assertNotFound();

    expect(Lead::withTrashed()->findOrFail($deleted->id)->user_id)->toBeNull();
});

test('the assignment note does not answer the customer', function () {
    $user = User::factory()->create();
    $lead = leadAssignmentWaitingLead();

    $this->actingAs($user)->patch(route('leads.assign-to-me', $lead));
    $this->patch(route('leads.bulk'), ['ids' => [$lead->id], 'user_id' => User::factory()->create()->id]);

    expect($lead->activities()->where('type', 'note')->count())->toBe(2)
        ->and(leadAssignmentQueueIds())->toBe([$lead->id])
        ->and(Lead::query()->awaitingWhatsappReply()->count())->toBe(1);

    $this->getJson(route('leads.whatsapp.messages', $lead))
        ->assertOk()
        ->assertJsonCount(1, 'messages');
});
