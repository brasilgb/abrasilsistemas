<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Uma conversa do atendimento institucional: um contato (chat_id) em uma sessão do WAHA.
 */
#[Fillable([
    'session',
    'chat_id',
    'phone',
    'name',
    'lead_id',
    'source_page',
    'last_message_at',
    'last_message_preview',
])]
class WhatsappConversation extends Model
{
    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
        ];
    }

    public function messages(): HasMany
    {
        return $this->hasMany(WhatsappMessage::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * Mensagens recebidas ainda não abertas no painel.
     */
    public function unreadMessages(): HasMany
    {
        return $this->messages()
            ->where('direction', WhatsappMessage::DIRECTION_INBOUND)
            ->whereNull('read_at');
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeForSession(Builder $query, string $session): void
    {
        $query->where('session', $session);
    }

    /**
     * Busca por nome, telefone ou identificador do contato.
     *
     * @param  Builder<self>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);

        if ($term === '') {
            return;
        }

        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';
        $digits = preg_replace('/\D+/', '', $term) ?? '';

        $query->where(function (Builder $query) use ($like, $digits) {
            $query->where('name', 'like', $like)->orWhere('chat_id', 'like', $like);

            if ($digits !== '') {
                $query->orWhere('phone', 'like', '%'.$digits.'%');
            }
        });
    }

    /**
     * Nome no WhatsApp; sem ele, o telefone formatado ("(51) 98888-7777"); sem telefone, o JID.
     */
    public function displayName(): string
    {
        if ($this->name) {
            return $this->name;
        }

        if ($this->phone === null) {
            return $this->chat_id;
        }

        return preg_match('/^55(\d{2})(\d{4,5})(\d{4})$/', $this->phone, $parts) === 1
            ? "({$parts[1]}) {$parts[2]}-{$parts[3]}"
            : '+'.$this->phone;
    }
}
