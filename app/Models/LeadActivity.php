<?php

namespace App\Models;

use Illuminate\Contracts\Cache\Lock;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

#[Fillable([
    'lead_id',
    'user_id',
    'type',
    'direction',
    'status',
    'contacted_at',
    'next_follow_up_at',
    'description',
    'provider_message_id',
    'message_status',
    'read_at',
])]
class LeadActivity extends Model
{
    use HasFactory;

    public const TYPES = [
        'note' => 'Nota',
        'whatsapp' => 'WhatsApp',
        'call' => 'Ligação',
        'email' => 'E-mail',
        'meeting' => 'Reunião',
    ];

    /**
     * Direção da mensagem WhatsApp, armazenada em direction:
     * inbound = cliente → empresa; outbound = empresa → cliente.
     * Nulo em atividades que não são mensagens e no legado anterior à coluna.
     */
    public const DIRECTION_INBOUND = 'inbound';

    public const DIRECTION_OUTBOUND = 'outbound';

    public const DIRECTIONS = [
        self::DIRECTION_INBOUND,
        self::DIRECTION_OUTBOUND,
    ];

    /**
     * Status técnicos de ACK do WAHA, armazenados em message_status.
     * Não confundir com o status comercial (Lead::STATUSES) do campo status.
     */
    public const MESSAGE_STATUS_PENDING = 'PENDING';

    public const MESSAGE_STATUS_SERVER = 'SERVER';

    public const MESSAGE_STATUS_DEVICE = 'DEVICE';

    public const MESSAGE_STATUS_READ = 'READ';

    public const MESSAGE_STATUS_PLAYED = 'PLAYED';

    public const MESSAGE_STATUS_ERROR = 'ERROR';

    /**
     * Progressão dos ACKs, em ordem. Um status só pode avançar nesta lista.
     */
    public const MESSAGE_STATUS_PROGRESSION = [
        self::MESSAGE_STATUS_PENDING,
        self::MESSAGE_STATUS_SERVER,
        self::MESSAGE_STATUS_DEVICE,
        self::MESSAGE_STATUS_READ,
        self::MESSAGE_STATUS_PLAYED,
    ];

    public const MESSAGE_STATUSES = [
        ...self::MESSAGE_STATUS_PROGRESSION,
        self::MESSAGE_STATUS_ERROR,
    ];

    /**
     * Prefixo do lock por provider_message_id. É o mesmo usado pelo WhatsappInboundController,
     * para que /inbound, /log e /outbound-external se serializem pela mesma mensagem.
     */
    public const WHATSAPP_MESSAGE_LOCK_PREFIX = 'whatsapp-inbound:';

    /**
     * Lock da mensagem WhatsApp: provider_message_id não é UNIQUE no banco, então a checagem de
     * duplicidade e a inserção precisam rodar dentro dele.
     */
    public static function whatsappMessageLock(string $providerMessageId): Lock
    {
        return Cache::lock(self::WHATSAPP_MESSAGE_LOCK_PREFIX.sha1($providerMessageId), 10);
    }

    /**
     * Atividade WhatsApp já registrada com o id do provedor, em qualquer lead.
     */
    public static function findWhatsappMessage(string $providerMessageId): ?self
    {
        return self::query()
            ->where('type', 'whatsapp')
            ->where('provider_message_id', $providerMessageId)
            ->first();
    }

    protected function casts(): array
    {
        return [
            'contacted_at' => 'datetime',
            'next_follow_up_at' => 'datetime',
            'read_at' => 'datetime',
        ];
    }

    /** Mesma normalização do próximo contato do lead (UTC; texto sem fuso é horário de Brasília). */
    public function setNextFollowUpAtAttribute(mixed $value): void
    {
        $this->attributes['next_follow_up_at'] = Lead::normalizeFollowUpAt($value);
    }

    /**
     * Regra de transição do message_status:
     * - sem status atual (ou desconhecido): aceita qualquer status válido;
     * - na progressão: só avança (READ nunca volta para DEVICE, por exemplo);
     * - ERROR só é aceito enquanto a mensagem não foi confirmada no aparelho
     *   (PENDING/SERVER); depois de DEVICE, um ERROR tardio é ignorado;
     * - a partir de ERROR, um ACK positivo além de PENDING é aceito, pois
     *   comprova que a mensagem de fato avançou.
     */
    public function canTransitionMessageStatusTo(string $status): bool
    {
        $current = $this->message_status;
        $progression = self::MESSAGE_STATUS_PROGRESSION;

        if ($current === null || ! in_array($current, self::MESSAGE_STATUSES, true)) {
            return true;
        }

        if ($status === self::MESSAGE_STATUS_ERROR) {
            return in_array($current, [self::MESSAGE_STATUS_PENDING, self::MESSAGE_STATUS_SERVER], true);
        }

        if ($current === self::MESSAGE_STATUS_ERROR) {
            return $status !== self::MESSAGE_STATUS_PENDING;
        }

        return array_search($status, $progression, true) > array_search($current, $progression, true);
    }

    /**
     * Mensagem recebida do cliente: não tem ciclo de entrega, então não recebe ACK.
     */
    public function isInbound(): bool
    {
        return $this->direction === self::DIRECTION_INBOUND;
    }

    /**
     * Mensagens WhatsApp recebidas que ainda não foram vistas no CRM (read_at nulo).
     * Mensagens enviadas (outbound) nunca entram: a leitura delas é o ACK em message_status.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function unreadInbound(Builder $query): void
    {
        $query
            ->where('type', 'whatsapp')
            ->where('direction', self::DIRECTION_INBOUND)
            ->whereNull('read_at');
    }

    /**
     * Mensagens trocadas pelo WhatsApp (com direction). O WhatsApp registrado manualmente
     * em "Registrar contato" não tem direction e fica só no histórico.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function conversation(Builder $query): void
    {
        $query
            ->where('type', 'whatsapp')
            ->whereIn('direction', self::DIRECTIONS);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
