<?php

use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\User;
use App\Services\CompanyWhatsappSettings;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;

/*
 * CRM-WA-07.1: o polling da conversa detecta a mensagem nova e a página faz a recarga parcial
 * da prop lead (X-Inertia-Partial-Data: lead) para o Histórico, sem segundo polling.
 */

function historySyncToken(): array
{
    return ['Authorization' => 'Bearer '.config('services.ab_prospect.token')];
}

function historySyncLead(): Lead
{
    return Lead::query()->create([
        'company_name' => 'Lead do histórico sincronizado',
        'product' => 'site',
        'status' => 'contacted',
        'whatsapp' => '5551995179173',
    ]);
}

/** Abertura da página: devolve a versão Inertia usada nas recargas parciais. */
function historySyncOpen(Lead $lead): string
{
    $response = test()->get(route('leads.edit', $lead))->assertOk();

    return (string) $response->viewData('page')['version'];
}

/** O que o router.reload({ only: ['lead'] }) da página pede ao servidor. */
function historySyncReload(Lead $lead, string $version): TestResponse
{
    return test()->get(route('leads.edit', $lead), [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => $version,
        'X-Inertia-Partial-Component' => 'leads/edit',
        'X-Inertia-Partial-Data' => 'lead',
    ])->assertOk();
}

/** @return array<int, array<string, mixed>> */
function historySyncActivities(TestResponse $response): array
{
    return $response->json('props.lead.activities');
}

/** Ciclo de polling incremental da conversa. */
function historySyncPoll(Lead $lead, int $since): TestResponse
{
    return test()->getJson(route('leads.whatsapp.messages', ['lead' => $lead, 'since' => $since]))->assertOk();
}

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-09-26 10:00:00'));

    app(CompanyWhatsappSettings::class)->save([
        'enabled' => true,
        'number' => '5551998931325',
        'provider' => CompanyWhatsappSettings::PROVIDER_WAHA,
        'session' => 'vetoros1-1',
    ]);
});

test('the partial lead reload returns only the lead prop with its activities', function () {
    $this->actingAs(User::factory()->create());
    $lead = historySyncLead();
    $version = historySyncOpen($lead);

    $response = historySyncReload($lead, $version);

    expect(array_keys($response->json('props')))->not->toContain('statuses', 'users', 'products', 'activityTypes')
        ->and($response->json('props.lead.id'))->toBe($lead->id)
        ->and(historySyncActivities($response))->toBe([]);
});

test('inbound, CRM outbound and phone outbound reach the history consistently with the conversation', function () {
    $this->actingAs(User::factory()->create());
    $lead = historySyncLead();
    $version = historySyncOpen($lead);
    $cursor = historySyncPoll($lead, 0)->json('cursor');

    // Cliente → empresa (webhook inbound).
    $this->postJson('/api/whatsapp/inbound', [
        'session' => 'vetoros1-1',
        'provider_message_id' => 'IN_SYNC_1',
        'from' => '555195179173',
        'push_name' => 'Cliente',
        'message' => 'Olá, quero um orçamento.',
    ], historySyncToken())->assertSuccessful();

    $this->travel(5)->seconds();

    // CRM → cliente (registro do n8n após o envio pelo CRM).
    $this->postJson("/api/prospects/{$lead->id}/whatsapp/log", [
        'message_id' => 'OUT_CRM_SYNC_1',
        'status' => 'PENDING',
        'message' => 'Resposta enviada pelo CRM.',
    ], historySyncToken())->assertCreated();

    $this->travel(5)->seconds();

    // Empresa → cliente enviada diretamente pelo celular.
    $this->postJson('/api/whatsapp/outbound-external', [
        'session' => 'vetoros1-1',
        'provider_message_id' => 'OUT_PHONE_SYNC_1',
        'to' => '555195179173@s.whatsapp.net',
        'message' => 'Complemento enviado pelo celular.',
        'status' => 'SERVER',
        'timestamp' => now()->getTimestamp(),
    ], historySyncToken())->assertSuccessful();

    $polled = historySyncPoll($lead, $cursor)->json('messages');
    $polledIds = array_column($polled, 'id');

    expect($polledIds)->toHaveCount(3);

    $history = historySyncActivities(historySyncReload($lead, $version));
    $historyIds = array_column($history, 'id');

    // Tudo o que a conversa exibe está no histórico, sem duplicar, do mais novo para o mais antigo.
    expect($historyIds)->toBe(array_reverse($polledIds))
        ->and($historyIds)->toBe(array_values(array_unique($historyIds)))
        ->and(array_column($history, 'direction'))->toBe(['outbound', 'outbound', 'inbound'])
        ->and(array_column($history, 'description'))->toBe(array_reverse(array_column($polled, 'description')));
});

test('an ACK-only change is polled again but does not create a history entry', function () {
    $this->actingAs(User::factory()->create());
    $lead = historySyncLead();

    $this->postJson("/api/prospects/{$lead->id}/whatsapp/log", [
        'message_id' => 'OUT_ACK_SYNC_1',
        'status' => 'PENDING',
        'message' => 'Mensagem aguardando entrega.',
    ], historySyncToken())->assertCreated();

    $version = historySyncOpen($lead);
    $before = historySyncActivities(historySyncReload($lead, $version));
    $cursor = historySyncPoll($lead, 0)->json('cursor');

    $this->travel(5)->seconds();

    $this->patchJson('/api/whatsapp/messages/OUT_ACK_SYNC_1/status', ['status' => 'DEVICE'], historySyncToken())
        ->assertSuccessful();

    // O polling devolve a mesma mensagem (id já conhecido) com o ACK novo...
    $polled = historySyncPoll($lead, $cursor)->json('messages');

    expect(array_column($polled, 'id'))->toBe([$before[0]['id']])
        ->and($polled[0]['message_status'])->toBe('DEVICE');

    // ...e o histórico continua com a mesma entrada única.
    $after = historySyncActivities(historySyncReload($lead, $version));

    expect(array_column($after, 'id'))->toBe(array_column($before, 'id'))
        ->and(LeadActivity::query()->where('lead_id', $lead->id)->count())->toBe(1)
        ->and($after[0]['message_status'])->toBe('DEVICE');
});

test('repeated polling cycles do not duplicate history activities', function () {
    $this->actingAs(User::factory()->create());
    $lead = historySyncLead();
    $version = historySyncOpen($lead);

    $this->postJson('/api/whatsapp/inbound', [
        'session' => 'vetoros1-1',
        'provider_message_id' => 'IN_REPEAT_SYNC_1',
        'from' => '555195179173',
        'push_name' => 'Cliente',
        'message' => 'Mensagem única.',
    ], historySyncToken())->assertSuccessful();

    // O cursor tem folga: a mesma mensagem volta em ciclos seguidos.
    $cursor = now()->getTimestamp();
    historySyncPoll($lead, $cursor);
    historySyncPoll($lead, $cursor);

    $history = historySyncActivities(historySyncReload($lead, $version));

    expect(array_column($history, 'id'))->toHaveCount(1)
        ->and(LeadActivity::query()->where('lead_id', $lead->id)->count())->toBe(1);
});

test('the partial reload of one lead never returns activities of another lead', function () {
    $this->actingAs(User::factory()->create());
    $first = historySyncLead();
    $second = Lead::query()->create([
        'company_name' => 'Outro lead',
        'status' => 'contacted',
        'whatsapp' => '5551988887777',
    ]);
    $version = historySyncOpen($first);

    $this->postJson('/api/whatsapp/inbound', [
        'session' => 'vetoros1-1',
        'provider_message_id' => 'IN_OTHER_SYNC_1',
        'from' => '555188887777',
        'push_name' => 'Outro cliente',
        'message' => 'Mensagem do outro lead.',
    ], historySyncToken())->assertSuccessful();

    expect(historySyncActivities(historySyncReload($first, $version)))->toBe([])
        ->and(historySyncActivities(historySyncReload($second, $version)))->toHaveCount(1);
});
