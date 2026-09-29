<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Reserva de execução de uma automação para um lead (CRM-WA-18). Só registra que a chave foi
 * reservada: não envia mensagem nem altera o lead. Regra comercial (lead encerrado, trial,
 * follow-up) fica com quem decide a elegibilidade, a partir do automation-state (CRM-WA-17).
 */
#[Fillable(['lead_id', 'automation_key', 'executed_at'])]
class LeadAutomationExecution extends Model
{
    public const KEY_MAX_LENGTH = 150;

    // Chave determinística montada pela automação, ex.: trial_expired:2026-10-05.
    public const KEY_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9._:-]*$/';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'executed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Lead, $this> */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * Reserva atômica: true só para quem gravou a chave. Sem SELECT prévio; a colisão vem do
     * UNIQUE (lead_id, automation_key), então duas requisições simultâneas não reservam as duas.
     */
    public static function reserve(Lead $lead, string $automationKey): bool
    {
        try {
            static::query()->create([
                'lead_id' => $lead->id,
                'automation_key' => $automationKey,
                'executed_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            return false;
        }

        return true;
    }
}
