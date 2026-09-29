<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ReserveLeadAutomationExecutionRequest;
use App\Models\Lead;
use App\Models\LeadAutomationExecution;
use Illuminate\Http\JsonResponse;

class LeadAutomationExecutionController extends Controller
{
    /**
     * Reserva a execução de uma automação para o lead. A primeira tentativa recebe 201 com
     * reserved = true; repetições recebem 200 com reserved = false (idempotência, não erro).
     * Lead removido (soft delete) não é resolvido pela rota: 404.
     */
    public function reserve(ReserveLeadAutomationExecutionRequest $request, Lead $lead): JsonResponse
    {
        $reserved = LeadAutomationExecution::reserve($lead, $request->validated('automation_key'));

        return response()->json(['reserved' => $reserved], $reserved ? 201 : 200);
    }
}
