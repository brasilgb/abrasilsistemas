<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use Illuminate\Http\JsonResponse;

class LeadAutomationStateController extends Controller
{
    /**
     * Estado comercial atual do lead para o n8n, somente leitura: não altera o lead, as atividades
     * nem last_contacted_at. As regras vêm do model (isClosed, trial_state, follow_up_state,
     * awaitingWhatsappReply). Lead removido (soft delete) não é resolvido pela rota: 404.
     * Instantes seguem a serialização padrão do app (ISO 8601 em UTC); datas do teste, Y-m-d.
     */
    public function show(Lead $lead): JsonResponse
    {
        $lastMessages = $lead->lastWhatsappMessageTimes();
        $trialExpiredKey = $lead->trialExpiredAutomationKey();

        return response()->json([
            'lead_id' => $lead->id,
            'status' => $lead->status,
            'is_closed' => $lead->isClosed(),
            'product' => $lead->product,
            'trial' => [
                'state' => $lead->trial_state,
                // Contrato do CRM-WA-17 mantido: dias de São Paulo, ends_at = último dia do teste.
                'started_at' => $lead->trial_started_at?->setTimezone(Lead::FOLLOW_UP_TIMEZONE)->toDateString(),
                'ends_at' => $lead->trialLastDay(),
            ],
            'follow_up' => [
                'state' => $lead->follow_up_state,
                'at' => $lead->next_follow_up_at,
            ],
            'whatsapp' => [
                'awaiting_reply' => $lead->isAwaitingWhatsappReply(),
                'last_outbound_at' => $lastMessages['outbound'],
                'last_inbound_at' => $lastMessages['inbound'],
            ],
            // Elegibilidade para o n8n consultar antes de reservar (automation-executions/reserve).
            // eligible é a regra completa do CRM-WA-24 (a mesma da listagem de candidatos);
            // automation_key continua sendo a chave do vencimento (CRM-WA-19), mesmo inelegível.
            'automations' => [
                'trial_expired' => [
                    'eligible' => $lead->isTrialExpiredAutomationCandidate(),
                    'automation_key' => $trialExpiredKey,
                    'message' => $lead->trialExpirationMessage(),
                ],
            ],
        ]);
    }
}
