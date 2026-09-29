<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Services\LeadWhatsappService;
use Illuminate\Http\JsonResponse;

class TrialExpiredAutomationCandidateController extends Controller
{
    /**
     * Leads elegíveis agora à mensagem automática de teste vencido (CRM-WA-24), para o n8n.
     * Somente leitura: não reserva, não envia e não altera o lead. A regra é Lead::
     * trialExpiredAutomationCandidates (fora do horário de atendimento, lista vazia). Quem envia
     * precisa reservar a automation_key antes (automation-executions/reserve).
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => Lead::trialExpiredAutomationCandidates()->map(fn (Lead $lead) => [
                'lead_id' => $lead->id,
                'automation_key' => $lead->trialExpiredAutomationKey(),
                'whatsapp' => LeadWhatsappService::recipientNumber($lead->whatsapp),
                'message' => $lead->trialExpirationMessage(),
                'trial_ends_at' => $lead->trial_ends_at,
            ])->all(),
        ]);
    }
}
