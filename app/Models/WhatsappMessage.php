<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'whatsapp_conversation_id',
    'direction',
    'body',
    'has_media',
    'provider_message_id',
    'status',
    'sent_by_user_id',
    'sent_at',
    'read_at',
])]
class WhatsappMessage extends Model
{
    public const DIRECTION_INBOUND = 'inbound';

    public const DIRECTION_OUTBOUND = 'outbound';

    protected function casts(): array
    {
        return [
            'has_media' => 'boolean',
            'sent_at' => 'datetime',
            'read_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(WhatsappConversation::class, 'whatsapp_conversation_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by_user_id');
    }

    /**
     * Mesma progressão de ACK do histórico de leads (PENDING → SERVER → DEVICE → READ → PLAYED).
     */
    public function canTransitionStatusTo(string $status): bool
    {
        return LeadActivity::messageStatusCanAdvance($this->status, $status);
    }
}
