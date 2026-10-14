<?php

use App\Models\Lead;
use App\Models\User;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use App\Services\CompanyWhatsappSettings;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

const INBOX_WAHA_URL = 'http://waha-inbox.test';
const INBOX_SESSION = 'atendimento-site';
const INBOX_HMAC_KEY = 'chave-hmac-de-teste';
const INBOX_CONTACT = '5551988887777@c.us';

beforeEach(function () {
    config([
        'services.waha.base_url' => INBOX_WAHA_URL,
        'services.waha.api_key' => 'waha-key',
        'services.waha.webhook_hmac_key' => INBOX_HMAC_KEY,
    ]);

    Http::preventStrayRequests();

    app(CompanyWhatsappSettings::class)->save([
        'enabled' => true,
        'number' => '51998931325',
        'provider' => 'waha',
        'session' => INBOX_SESSION,
    ]);
});

/**
 * Envia um evento ao webhook assinado como o WAHA assina (HMAC-SHA512 do corpo).
 *
 * @param  array<string, mixed>  $payload
 */
function wahaWebhook(string $event, array $payload, string $session = INBOX_SESSION, ?string $key = INBOX_HMAC_KEY)
{
    $body = json_encode(['event' => $event, 'session' => $session, 'payload' => $payload]);
    $headers = ['Content-Type' => 'application/json'];

    if ($key !== null) {
        $headers['X-Webhook-Hmac'] = hash_hmac('sha512', $body, $key);
        $headers['X-Webhook-Hmac-Algorithm'] = 'sha512';
    }

    return test()->call('POST', route('api.whatsapp.waha.webhook'), [], [], [], test()->transformHeadersToServerVars($headers), $body);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function inboundPayload(array $overrides = []): array
{
    return array_merge([
        'id' => 'false_'.INBOX_CONTACT.'_3EB0AAA111',
        'timestamp' => 1760000000,
        'from' => INBOX_CONTACT,
        'to' => '5551998931325@c.us',
        'fromMe' => false,
        'body' => 'Olá, quero falar com a ABrasil Sistemas'."\n\nPágina: /automacao-de-processos",
        'hasMedia' => false,
        '_data' => ['notifyName' => 'Maria Souza'],
    ], $overrides);
}

test('the webhook is disabled without an hmac key', function () {
    config(['services.waha.webhook_hmac_key' => null]);

    wahaWebhook('message', inboundPayload())->assertStatus(503);

    expect(WhatsappMessage::query()->count())->toBe(0);
});

test('the webhook rejects a missing or wrong signature', function () {
    wahaWebhook('message', inboundPayload(), key: null)->assertStatus(401);
    wahaWebhook('message', inboundPayload(), key: 'outra-chave')->assertStatus(401);

    expect(WhatsappMessage::query()->count())->toBe(0);
});

test('an inbound message opens a conversation with contact, origin page and unread message', function () {
    $lead = Lead::query()->create(['company_name' => 'Empresa da Maria', 'product' => 'site', 'whatsapp' => '51988887777']);

    wahaWebhook('message', inboundPayload())->assertOk()->assertJson(['success' => true]);

    $conversation = WhatsappConversation::query()->sole();

    expect($conversation->session)->toBe(INBOX_SESSION)
        ->and($conversation->chat_id)->toBe(INBOX_CONTACT)
        ->and($conversation->phone)->toBe('5551988887777')
        ->and($conversation->name)->toBe('Maria Souza')
        ->and($conversation->source_page)->toBe('/automacao-de-processos')
        ->and($conversation->lead_id)->toBe($lead->id)
        ->and($conversation->last_message_preview)->toContain('Olá, quero falar');

    $message = WhatsappMessage::query()->sole();

    expect($message->direction)->toBe(WhatsappMessage::DIRECTION_INBOUND)
        ->and($message->provider_message_id)->toBe('3EB0AAA111')
        ->and($message->read_at)->toBeNull()
        ->and($message->sent_at->timestamp)->toBe(1760000000);
});

test('message and message.any for the same message are stored once', function () {
    wahaWebhook('message', inboundPayload())->assertOk();
    wahaWebhook('message.any', inboundPayload())->assertOk()->assertJson(['reason' => 'duplicate']);

    expect(WhatsappMessage::query()->count())->toBe(1);
});

test('events from other sessions of the shared WAHA are ignored', function () {
    wahaWebhook('message', inboundPayload(), session: 'vetoros1-1')
        ->assertOk()
        ->assertJson(['success' => false, 'reason' => 'session_mismatch']);

    expect(WhatsappConversation::query()->count())->toBe(0);
});

test('groups, status broadcasts and empty messages are ignored', function () {
    wahaWebhook('message', inboundPayload(['id' => 'g1', 'from' => '120363000000@g.us']))->assertJson(['reason' => 'chat_not_supported']);
    wahaWebhook('message', inboundPayload(['id' => 's1', 'from' => 'status@broadcast']))->assertJson(['reason' => 'chat_not_supported']);
    wahaWebhook('message', inboundPayload(['id' => 'e1', 'body' => '   ']))->assertJson(['reason' => 'empty_message']);

    expect(WhatsappConversation::query()->count())->toBe(0);
});

test('media messages are recorded with a placeholder', function () {
    wahaWebhook('message', inboundPayload(['body' => '', 'hasMedia' => true]))->assertOk();

    $message = WhatsappMessage::query()->sole();

    expect($message->has_media)->toBeTrue()
        ->and($message->body)->toContain('Mídia');
});

test('messages sent from the company phone are stored as outbound and already read', function () {
    wahaWebhook('message.any', inboundPayload([
        'id' => 'true_'.INBOX_CONTACT.'_3EB0BBB222',
        'from' => '5551998931325@c.us',
        'to' => INBOX_CONTACT,
        'fromMe' => true,
        'body' => 'Respondido pelo celular.',
        'ackName' => 'SERVER',
    ]))->assertOk();

    $message = WhatsappMessage::query()->sole();

    expect($message->direction)->toBe(WhatsappMessage::DIRECTION_OUTBOUND)
        ->and($message->status)->toBe('SERVER')
        ->and($message->read_at)->not->toBeNull()
        ->and(WhatsappConversation::query()->sole()->chat_id)->toBe(INBOX_CONTACT);
});

test('acks advance the message status and never regress it', function () {
    wahaWebhook('message.any', inboundPayload(['id' => 'true_'.INBOX_CONTACT.'_3EB0CCC333', 'fromMe' => true, 'to' => INBOX_CONTACT, 'ackName' => 'SERVER']));

    wahaWebhook('message.ack', ['id' => 'true_'.INBOX_CONTACT.'_3EB0CCC333', 'ack' => 3, 'ackName' => 'READ'])->assertJson(['success' => true]);
    expect(WhatsappMessage::query()->sole()->status)->toBe('READ');

    wahaWebhook('message.ack', ['id' => 'true_'.INBOX_CONTACT.'_3EB0CCC333', 'ack' => 2, 'ackName' => 'DEVICE'])->assertJson(['reason' => 'ack_ignored']);
    expect(WhatsappMessage::query()->sole()->status)->toBe('READ');

    wahaWebhook('message.ack', ['id' => 'desconhecida', 'ackName' => 'READ'])->assertJson(['reason' => 'message_not_found']);
});

test('the inbox lists only the configured session and marks the opened conversation as read', function () {
    wahaWebhook('message', inboundPayload());
    $other = WhatsappConversation::query()->create(['session' => 'outra-sessao', 'chat_id' => '5511911112222@c.us']);
    $conversation = WhatsappConversation::query()->where('session', INBOX_SESSION)->sole();

    $this->actingAs(User::factory()->create())
        ->get('/atendimento')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('atendimento/index')
            ->where('session', INBOX_SESSION)
            ->where('webhookEnabled', true)
            ->where('unreadTotal', 1)
            ->has('conversations', 1)
            ->where('conversations.0.id', $conversation->id)
            ->where('conversations.0.unread', 1)
            ->where('active', null));

    $this->actingAs(User::factory()->create())
        ->get('/atendimento?conversa='.$conversation->id)
        ->assertInertia(fn (Assert $page) => $page
            ->where('active.id', $conversation->id)
            ->where('active.source_page', '/automacao-de-processos')
            ->has('active.messages', 1)
            ->where('conversations.0.unread', 0)
            ->where('unreadTotal', 0));

    expect(WhatsappMessage::query()->whereNull('read_at')->count())->toBe(0);

    $this->actingAs(User::factory()->create())
        ->get('/atendimento?conversa='.$other->id)
        ->assertInertia(fn (Assert $page) => $page->where('active', null));
});

test('the inbox search filters by name and phone', function () {
    wahaWebhook('message', inboundPayload());
    wahaWebhook('message', inboundPayload(['id' => 'false_5511900001111@c.us_X2', 'from' => '5511900001111@c.us', '_data' => ['notifyName' => 'João Lima']]));
    $admin = User::factory()->create();

    $this->actingAs($admin)->get('/atendimento?busca=maria')
        ->assertInertia(fn (Assert $page) => $page->has('conversations', 1)->where('conversations.0.name', 'Maria Souza'));

    $this->actingAs($admin)->get('/atendimento?busca=(11) 90000')
        ->assertInertia(fn (Assert $page) => $page->has('conversations', 1)->where('conversations.0.name', 'João Lima'));
});

test('a reply is sent through WAHA and the later webhook echo is not duplicated', function () {
    wahaWebhook('message', inboundPayload());
    $conversation = WhatsappConversation::query()->sole();
    $admin = User::factory()->create(['name' => 'Ana Atendente']);

    Http::fake([INBOX_WAHA_URL.'/api/sendText' => Http::response(['key' => ['id' => '3EB0DDD444', 'fromMe' => true]], 201)]);

    $this->actingAs($admin)
        ->post(route('atendimento.reply', $conversation), ['body' => '  Olá Maria, como podemos ajudar?  '])
        ->assertRedirect('/atendimento?conversa='.$conversation->id);

    Http::assertSent(fn (Request $request) => $request->url() === INBOX_WAHA_URL.'/api/sendText'
        && $request['session'] === INBOX_SESSION
        && $request['chatId'] === INBOX_CONTACT
        && $request['text'] === 'Olá Maria, como podemos ajudar?'
        && $request->hasHeader('X-Api-Key', 'waha-key'));

    $reply = WhatsappMessage::query()->where('direction', WhatsappMessage::DIRECTION_OUTBOUND)->sole();

    expect($reply->provider_message_id)->toBe('3EB0DDD444')
        ->and($reply->status)->toBe('PENDING')
        ->and($reply->sent_by_user_id)->toBe($admin->id);

    wahaWebhook('message.any', inboundPayload([
        'id' => 'true_'.INBOX_CONTACT.'_3EB0DDD444',
        'fromMe' => true,
        'to' => INBOX_CONTACT,
        'body' => 'Olá Maria, como podemos ajudar?',
    ]))->assertJson(['reason' => 'duplicate']);

    wahaWebhook('message.ack', ['id' => 'true_'.INBOX_CONTACT.'_3EB0DDD444', 'ackName' => 'DEVICE'])->assertJson(['success' => true]);

    expect(WhatsappMessage::query()->where('direction', WhatsappMessage::DIRECTION_OUTBOUND)->sole()->status)->toBe('DEVICE');
});

test('a WAHA failure on reply shows an error and stores nothing', function () {
    wahaWebhook('message', inboundPayload());
    $conversation = WhatsappConversation::query()->sole();

    Http::fake([INBOX_WAHA_URL.'/api/sendText' => Http::response(['error' => 'down'], 500)]);

    $this->actingAs(User::factory()->create())
        ->from('/atendimento?conversa='.$conversation->id)
        ->post(route('atendimento.reply', $conversation), ['body' => 'Teste'])
        ->assertSessionHasErrors('body');

    expect(WhatsappMessage::query()->where('direction', WhatsappMessage::DIRECTION_OUTBOUND)->count())->toBe(0);
});

test('replies are refused for conversations of another session', function () {
    $other = WhatsappConversation::query()->create(['session' => 'outra-sessao', 'chat_id' => '5511911112222@c.us']);

    $this->actingAs(User::factory()->create())
        ->post(route('atendimento.reply', $other), ['body' => 'Teste'])
        ->assertNotFound();

    Http::assertNothingSent();
});

test('only admins can open the inbox', function () {
    $this->get('/atendimento')->assertRedirect(route('login'));
    $this->actingAs(User::factory()->reader()->create())->get('/atendimento')->assertForbidden();
});
