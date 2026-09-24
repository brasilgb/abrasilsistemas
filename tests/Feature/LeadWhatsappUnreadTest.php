<?php

use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

function unreadLead(string $whatsapp = '5551995179173', array $overrides = []): Lead
{
    return Lead::query()->create(array_merge([
        'company_name' => 'Lead com mensagens',
        'product' => 'site',
        'status' => 'contacted',
        'whatsapp' => $whatsapp,
    ], $overrides));
}

function unreadInboundActivity(Lead $lead, string $providerMessageId, array $overrides = []): LeadActivity
{
    return $lead->activities()->create(array_merge([
        'type' => 'whatsapp',
        'direction' => LeadActivity::DIRECTION_INBOUND,
        'contacted_at' => now(),
        'description' => 'Mensagem do cliente.',
        'provider_message_id' => $providerMessageId,
    ], $overrides));
}

function unreadOutboundActivity(Lead $lead, string $providerMessageId): LeadActivity
{
    return $lead->activities()->create([
        'type' => 'whatsapp',
        'direction' => LeadActivity::DIRECTION_OUTBOUND,
        'contacted_at' => now(),
        'description' => 'Mensagem da empresa.',
        'provider_message_id' => $providerMessageId,
        'message_status' => LeadActivity::MESSAGE_STATUS_SERVER,
    ]);
}

function postUnreadInbound(array $overrides = [])
{
    return test()->postJson('/api/whatsapp/inbound', array_merge([
        'session' => 'vetoros1-1',
        'provider_message_id' => '3EB00B502BEA742D043912',
        'from' => '555195179173',
        'message' => 'Olá, quero saber mais.',
    ], $overrides), ['Authorization' => 'Bearer '.config('services.ab_prospect.token')]);
}

test('an inbound WhatsApp message received from the API is born unread', function () {
    $lead = unreadLead();

    postUnreadInbound()->assertCreated();

    $activity = $lead->activities()->sole();

    expect($activity->read_at)->toBeNull()
        ->and($activity->message_status)->toBeNull()
        ->and($lead->unreadWhatsappMessages()->count())->toBe(1);
});

test('an inbound WhatsApp message logged through the n8n log endpoint is born unread', function () {
    $lead = unreadLead();

    $this->postJson("/api/prospects/{$lead->id}/whatsapp/log", [
        'message_id' => '3EB0F27EBBA9D67C6210DD',
        'direction' => 'inbound',
        'message' => 'Resposta do cliente.',
    ], ['Authorization' => 'Bearer '.config('services.ab_prospect.token')])->assertSuccessful();

    expect($lead->activities()->sole()->read_at)->toBeNull()
        ->and($lead->unreadWhatsappMessages()->count())->toBe(1);
});

test('outbound, legacy and non WhatsApp activities are not counted as unread', function () {
    $user = User::factory()->create();
    $lead = unreadLead();

    unreadOutboundActivity($lead, 'true_out_1');
    $lead->activities()->create([
        'type' => 'whatsapp',
        'contacted_at' => now(),
        'description' => 'Registro legado, sem direção.',
    ]);
    $lead->activities()->create([
        'user_id' => $user->id,
        'type' => 'note',
        'direction' => LeadActivity::DIRECTION_INBOUND,
        'description' => 'Nota que não é mensagem WhatsApp.',
    ]);

    $this->actingAs($user)->get(route('leads.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('leads.data.0.id', $lead->id)
            ->where('leads.data.0.unread_whatsapp_messages_count', 0));
});

test('the leads listing shows the unread inbound count of each lead', function () {
    $user = User::factory()->create();
    $withMessages = unreadLead('5551995179173', ['company_name' => 'Com mensagens']);
    $withoutMessages = unreadLead('5551988887777', ['company_name' => 'Sem mensagens']);

    unreadInboundActivity($withMessages, 'in_1');
    unreadInboundActivity($withMessages, 'in_2');
    unreadInboundActivity($withMessages, 'in_old', ['read_at' => now()->subDay()]);
    unreadOutboundActivity($withMessages, 'out_1');
    unreadOutboundActivity($withoutMessages, 'out_2');

    $counts = fn (array $leads) => collect($leads)->pluck('unread_whatsapp_messages_count', 'id')->all();

    $this->actingAs($user)->get(route('leads.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            // == compara pares chave/valor sem depender da ordem da listagem.
            ->where('leads.data', fn ($leads) => $counts($leads->all()) == [
                $withMessages->id => 2,
                $withoutMessages->id => 0,
            ])
            ->where('kanbanLeads', fn ($leads) => $counts($leads->all())[$withMessages->id] === 2
                && $counts($leads->all())[$withoutMessages->id] === 0)
            ->where('taskLeads.without_follow_up', fn ($leads) => $counts($leads->all())[$withMessages->id] === 2));
});

test('the unread count in the leads listing does not cause N+1 queries', function () {
    $user = User::factory()->create();

    $queriesFor = function () use ($user) {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($user)->get(route('leads.index'))->assertOk();
        DB::disableQueryLog();

        return collect(DB::getQueryLog())
            ->filter(fn (array $query) => str_contains($query['query'], 'lead_activities'))
            ->count();
    };

    $lead = unreadLead('5551900000000');
    unreadInboundActivity($lead, 'in_0');
    $withOneLead = $queriesFor();

    foreach (range(1, 5) as $i) {
        unreadInboundActivity(unreadLead("555190000000{$i}"), "in_{$i}");
    }

    expect($queriesFor())->toBe($withOneLead);
});

test('opening the lead marks its pending inbound messages as read', function () {
    $user = User::factory()->create();
    $lead = unreadLead();

    $pending = unreadInboundActivity($lead, 'in_1');
    $alreadyRead = unreadInboundActivity($lead, 'in_old', ['read_at' => now()->subDay()->startOfSecond()]);
    $outbound = unreadOutboundActivity($lead, 'out_1');

    // A visita que marca como lida ainda recebe read_at nulo, para destacar a mensagem nova.
    $this->actingAs($user)->get(route('leads.edit', $lead))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('leads/edit')
            ->where('lead.activities', fn ($activities) => collect($activities)->firstWhere('id', $pending->id)['read_at'] === null));

    expect($pending->fresh()->read_at)->not->toBeNull()
        ->and($alreadyRead->fresh()->read_at->equalTo($alreadyRead->read_at))->toBeTrue()
        ->and($outbound->fresh()->read_at)->toBeNull()
        ->and($outbound->fresh()->message_status)->toBe(LeadActivity::MESSAGE_STATUS_SERVER)
        ->and($pending->fresh()->message_status)->toBeNull()
        ->and($lead->unreadWhatsappMessages()->count())->toBe(0);

    $this->actingAs($user)->get(route('leads.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('leads.data.0.unread_whatsapp_messages_count', 0));

    $this->actingAs($user)->get(route('leads.edit', $lead))
        ->assertInertia(fn (Assert $page) => $page
            ->where('lead.activities', fn ($activities) => collect($activities)->firstWhere('id', $pending->id)['read_at'] !== null));
});

test('opening a lead does not mark messages of other leads as read', function () {
    $user = User::factory()->create();
    $opened = unreadLead('5551995179173', ['company_name' => 'Aberto']);
    $other = unreadLead('5551988887777', ['company_name' => 'Outro']);

    unreadInboundActivity($opened, 'in_opened');
    $otherMessage = unreadInboundActivity($other, 'in_other');

    $this->actingAs($user)->get(route('leads.edit', $opened))->assertOk();

    expect($opened->unreadWhatsappMessages()->count())->toBe(0)
        ->and($otherMessage->fresh()->read_at)->toBeNull()
        ->and($other->unreadWhatsappMessages()->count())->toBe(1);
});

test('a user without access to leads cannot mark messages as read', function () {
    $lead = unreadLead();
    $message = unreadInboundActivity($lead, 'in_1');

    $this->actingAs(User::factory()->reader()->create())
        ->get(route('leads.edit', $lead))
        ->assertForbidden();

    expect($message->fresh()->read_at)->toBeNull();
});

test('a repeated inbound delivery stays idempotent and does not reopen a read message', function () {
    $user = User::factory()->create();
    $lead = unreadLead();

    postUnreadInbound()->assertCreated();
    postUnreadInbound()->assertOk()->assertJsonPath('idempotent', true);

    expect($lead->activities()->count())->toBe(1)
        ->and($lead->unreadWhatsappMessages()->count())->toBe(1);

    $this->actingAs($user)->get(route('leads.edit', $lead))->assertOk();

    postUnreadInbound()->assertOk()->assertJsonPath('idempotent', true);

    expect($lead->activities()->count())->toBe(1)
        ->and($lead->unreadWhatsappMessages()->count())->toBe(0);
});

test('a new inbound message after reading makes the lead unread again', function () {
    $user = User::factory()->create();
    $lead = unreadLead();

    postUnreadInbound(['provider_message_id' => 'in_1'])->assertCreated();
    $this->actingAs($user)->get(route('leads.edit', $lead))->assertOk();

    postUnreadInbound(['provider_message_id' => 'in_2'])->assertCreated();

    expect($lead->unreadWhatsappMessages()->count())->toBe(1)
        ->and($lead->unreadWhatsappMessages()->sole()->provider_message_id)->toBe('in_2');
});
