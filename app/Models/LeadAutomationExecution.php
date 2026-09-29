<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Reserva de execução de uma automação para um lead (CRM-WA-18). Só registra que a chave foi
 * reservada: não envia mensagem nem altera o lead. Regra comercial (lead encerrado, trial,
 * follow-up) fica com quem decide a elegibilidade, a partir do automation-state (CRM-WA-17).
 *
 * @property int $lead_id
 * @property string $automation_key
 * @property int|null $lead_activity_id
 * @property CarbonInterface $executed_at
 */
#[Fillable(['lead_id', 'automation_key', 'executed_at', 'lead_activity_id'])]
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
     * Mensagem WhatsApp enviada por esta execução (CRM-WA-28), gravada pelo /whatsapp/log.
     *
     * @return BelongsTo<LeadActivity, $this>
     */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(LeadActivity::class, 'lead_activity_id');
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

    /**
     * Vincula a mensagem outbound à execução (lead, chave) ainda sem mensagem (CRM-WA-28). O UPDATE
     * condicional é atômico: nunca troca um vínculo existente, então repetições e a mesma mensagem
     * registrada de novo não mudam nada. Sem execução reservada para a chave, não faz nada. Mensagem
     * já vinculada a outra execução: o UNIQUE de lead_activity_id recusa e nada muda.
     */
    public static function linkActivity(Lead $lead, string $automationKey, LeadActivity $activity): bool
    {
        if ((int) $activity->lead_id !== (int) $lead->id
            || $activity->type !== 'whatsapp'
            || $activity->direction !== LeadActivity::DIRECTION_OUTBOUND) {
            return false;
        }

        try {
            return static::query()
                ->where('lead_id', $lead->id)
                ->where('automation_key', $automationKey)
                ->whereNull('lead_activity_id')
                ->update(['lead_activity_id' => $activity->id]) === 1;
        } catch (UniqueConstraintViolationException) {
            return false;
        }
    }
}
