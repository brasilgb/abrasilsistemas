<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\User;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;

/**
 * Atendimento WhatsApp institucional: grava os eventos do WAHA (mensagens e ACKs) e envia
 * as respostas do painel. Usa a sessão do "WhatsApp da empresa" (CompanyWhatsappSettings);
 * eventos de outras sessões do WAHA compartilhado são ignorados, o que isola este atendimento.
 */
class WhatsappInbox
{
    /** Linha "Página: /caminho" que o site acrescenta à mensagem pré-preenchida do WhatsApp. */
    private const SOURCE_PAGE_PATTERN = '/^\s*P[áa]gina:\s*(\/[^\s]*)\s*$/miu';

    public function __construct(
        private readonly CompanyWhatsappSettings $settings,
        private readonly WahaService $waha,
    ) {}

    public function session(): ?string
    {
        return $this->settings->current()['session'];
    }

    /**
     * Processa um webhook do WAHA. Devolve o motivo quando o evento é ignorado (null = gravado).
     *
     * @param  array<string, mixed>  $event
     */
    public function handleEvent(array $event): ?string
    {
        $session = $this->session();

        if ($session === null) {
            return 'session_not_configured';
        }

        if (($event['session'] ?? null) !== $session) {
            return 'session_mismatch';
        }

        $payload = is_array($event['payload'] ?? null) ? $event['payload'] : [];

        return match ($event['event'] ?? null) {
            'message', 'message.any' => $this->recordMessage($session, $payload),
            'message.ack' => $this->recordAck($payload),
            default => 'event_not_supported',
        };
    }

    /**
     * Envia uma resposta do painel pelo WAHA e grava a mensagem como enviada (status PENDING).
     *
     * @throws WahaException
     */
    public function reply(WhatsappConversation $conversation, string $text, User $user): WhatsappMessage
    {
        $providerId = $this->waha->sendText($conversation->session, $conversation->chat_id, $text);
        $now = CarbonImmutable::now();

        $message = $this->storeMessage($conversation, [
            'direction' => WhatsappMessage::DIRECTION_OUTBOUND,
            'body' => $text,
            'provider_message_id' => self::messageKey($providerId),
            'status' => LeadActivity::MESSAGE_STATUS_PENDING,
            'sent_by_user_id' => $user->id,
            'sent_at' => $now,
            'read_at' => $now,
        ]);

        $this->touchConversation($conversation, $message);

        return $message;
    }

    /**
     * Vincula a uma solicitação do site as conversas do mesmo número que ainda não têm solicitação
     * (quando a pessoa falou pelo WhatsApp antes de preencher o formulário). Devolve quantas vinculou.
     */
    public function linkSiteRequest(Lead $lead): int
    {
        $session = $this->session();
        $phone = CompanyWhatsappSettings::normalizeNumber($lead->whatsapp);

        if ($session === null || $phone === null || ! $lead->isSiteRequest()) {
            return 0;
        }

        return WhatsappConversation::query()
            ->forSession($session)
            // Sem vínculo, ou ligada a um prospect antigo do CRM: nunca troca de uma solicitação para outra.
            ->whereDoesntHave('lead', fn ($query) => $query->siteRequests())
            ->whereIn('phone', $this->phoneVariants($phone))
            ->update(['lead_id' => $lead->id]);
    }

    /**
     * Marca como lidas as mensagens recebidas da conversa.
     */
    public function markAsRead(WhatsappConversation $conversation): void
    {
        $conversation->unreadMessages()->update(['read_at' => now()]);
    }

    /**
     * Ids do WhatsApp chegam em formatos diferentes ("true_5551…@c.us_3EB0AB…" no webhook,
     * "3EB0AB…" na resposta do sendText). O trecho final é a chave da mensagem e é o que gravamos.
     */
    public static function messageKey(?string $id): ?string
    {
        if (blank($id)) {
            return null;
        }

        return Str::afterLast((string) $id, '_');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function recordMessage(string $session, array $payload): ?string
    {
        $fromMe = (bool) ($payload['fromMe'] ?? false);
        $chatId = (string) ($fromMe ? ($payload['to'] ?? '') : ($payload['from'] ?? ''));

        if ($chatId === '' || ! $this->isPrivateChat($chatId)) {
            return 'chat_not_supported';
        }

        $hasMedia = (bool) ($payload['hasMedia'] ?? false);
        $body = trim((string) ($payload['body'] ?? ''));

        if ($body === '' && ! $hasMedia) {
            return 'empty_message';
        }

        $key = self::messageKey(is_string($payload['id'] ?? null) ? $payload['id'] : null);

        if ($key !== null && WhatsappMessage::query()->where('provider_message_id', $key)->exists()) {
            return 'duplicate';
        }

        $conversation = $this->conversationFor($session, $chatId);
        $timestamp = is_numeric($payload['timestamp'] ?? null) ? CarbonImmutable::createFromTimestamp((int) $payload['timestamp']) : CarbonImmutable::now();

        if (! $fromMe) {
            $this->identifyContact($conversation, $payload, $body);
        }

        $message = $this->storeMessage($conversation, [
            'direction' => $fromMe ? WhatsappMessage::DIRECTION_OUTBOUND : WhatsappMessage::DIRECTION_INBOUND,
            'body' => $body !== '' ? $body : '[Mídia — abra no WhatsApp para visualizar]',
            'has_media' => $hasMedia,
            'provider_message_id' => $key,
            'status' => $fromMe ? $this->ackName($payload) : null,
            // Enviadas pelo celular/WhatsApp Web da empresa já foram vistas por quem enviou.
            'read_at' => $fromMe ? $timestamp : null,
            'sent_at' => $timestamp,
        ]);

        if ($message === null) {
            return 'duplicate';
        }

        $this->touchConversation($conversation, $message);

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function recordAck(array $payload): ?string
    {
        $status = $this->ackName($payload);
        $key = self::messageKey(is_string($payload['id'] ?? null) ? $payload['id'] : null);

        if ($status === null || $key === null) {
            return 'invalid_ack';
        }

        $message = WhatsappMessage::query()->where('provider_message_id', $key)->first();

        if ($message === null) {
            return 'message_not_found';
        }

        if ($message->status === $status || ! $message->canTransitionStatusTo($status)) {
            return 'ack_ignored';
        }

        $message->forceFill(['status' => $status])->save();

        return null;
    }

    private function conversationFor(string $session, string $chatId): WhatsappConversation
    {
        $phone = str_ends_with($chatId, '@c.us') || str_ends_with($chatId, '@s.whatsapp.net')
            ? CompanyWhatsappConnection::normalizeJid($chatId)
            : null;

        $conversation = WhatsappConversation::query()->createOrFirst(
            ['session' => $session, 'chat_id' => $chatId],
            ['phone' => $phone],
        );

        if ($conversation->wasRecentlyCreated && $phone !== null) {
            // Só solicitações do site: prospects comerciais (VetorLeads) não entram no atendimento.
            $lead = Lead::query()->siteRequests()->whereIn('whatsapp', $this->phoneVariants($phone))->oldest('id')->first();

            if ($lead !== null) {
                $conversation->forceFill(['lead_id' => $lead->id])->save();
            }
        }

        return $conversation;
    }

    /**
     * Nome exibido no WhatsApp e página do site de onde o contato veio (só na primeira vez).
     *
     * @param  array<string, mixed>  $payload
     */
    private function identifyContact(WhatsappConversation $conversation, array $payload, string $body): void
    {
        $name = data_get($payload, '_data.notifyName') ?? data_get($payload, '_data.pushName') ?? data_get($payload, 'notifyName');

        if (is_string($name) && trim($name) !== '') {
            $conversation->name = Str::limit(trim($name), 250, '');
        }

        if ($conversation->source_page === null && preg_match(self::SOURCE_PAGE_PATTERN, $body, $matches) === 1) {
            $conversation->source_page = Str::limit($matches[1], 250, '');
        }

        if ($conversation->isDirty()) {
            $conversation->save();
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function storeMessage(WhatsappConversation $conversation, array $attributes): ?WhatsappMessage
    {
        try {
            return $conversation->messages()->create($attributes);
        } catch (UniqueConstraintViolationException) {
            // A mesma mensagem chegou por message e message.any ao mesmo tempo: a outra requisição já gravou.
            return null;
        }
    }

    private function touchConversation(WhatsappConversation $conversation, WhatsappMessage $message): void
    {
        if ($conversation->last_message_at !== null && $conversation->last_message_at->greaterThan($message->sent_at)) {
            return;
        }

        $conversation->forceFill([
            'last_message_at' => $message->sent_at,
            'last_message_preview' => Str::limit(preg_replace('/\s+/', ' ', $message->body) ?? '', 120),
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function ackName(array $payload): ?string
    {
        $name = strtoupper((string) ($payload['ackName'] ?? ''));

        return in_array($name, LeadActivity::MESSAGE_STATUSES, true) ? $name : null;
    }

    /**
     * Conversas individuais só: grupos, status e canais ficam fora do atendimento.
     */
    private function isPrivateChat(string $chatId): bool
    {
        return (bool) preg_match('/@(c\.us|s\.whatsapp\.net|lid)$/', $chatId) && $chatId !== 'status@broadcast';
    }

    /**
     * Celulares brasileiros podem estar gravados com ou sem o nono dígito e com ou sem o 55.
     *
     * @return list<string>
     */
    private function phoneVariants(string $phone): array
    {
        $variants = [$phone];

        if (str_starts_with($phone, '55')) {
            $variants[] = substr($phone, 2);
        }

        if (strlen($phone) === 13 && str_starts_with($phone, '55') && $phone[4] === '9') {
            $withoutNinth = substr($phone, 0, 4).substr($phone, 5);
            $variants[] = $withoutNinth;
            $variants[] = substr($withoutNinth, 2);
        }

        if (strlen($phone) === 12 && str_starts_with($phone, '55')) {
            $withNinth = substr($phone, 0, 4).'9'.substr($phone, 4);
            $variants[] = $withNinth;
            $variants[] = substr($withNinth, 2);
        }

        return array_values(array_unique($variants));
    }
}
