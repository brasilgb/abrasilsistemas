<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LogWhatsappActivityRequest;
use App\Models\Lead;
use App\Models\LeadActivity;
use Illuminate\Http\JsonResponse;

class WhatsappActivityController extends Controller
{
    /**
     * Registra uma mensagem WhatsApp vinda do n8n. Sem direction no payload (contrato do
     * workflow de envio), a mensagem é outbound. Inbound não guarda message_status: o fato de
     * ter sido recebida é representado pela direction, não por um status de entrega.
     */
    public function store(LogWhatsappActivityRequest $request, Lead $lead): JsonResponse
    {
        $data = $request->validated();
        $messageId = $data['message_id'] ?? null;
        $direction = $data['direction'] ?? LeadActivity::DIRECTION_OUTBOUND;
        $inbound = $direction === LeadActivity::DIRECTION_INBOUND;

        if ($messageId !== null) {
            $existing = $lead->activities()
                ->where('provider_message_id', $messageId)
                ->first();

            if ($existing !== null) {
                return response()->json([
                    'success' => true,
                    'message' => $inbound ? 'Mensagem recebida já estava registrada.' : 'Envio de WhatsApp já estava registrado.',
                    'activity_id' => $existing->id,
                    'idempotent' => true,
                ]);
            }
        }

        $activity = $lead->activities()->create([
            'user_id' => null,
            'type' => 'whatsapp',
            'direction' => $direction,
            'status' => null,
            'contacted_at' => now(),
            'description' => $data['message'],
            'provider_message_id' => $messageId,
            'message_status' => $inbound ? null : $data['status'],
        ]);

        $lead->forceFill(['last_contacted_at' => $activity->contacted_at])->save();

        return response()->json([
            'success' => true,
            'message' => $inbound ? 'Mensagem recebida registrada.' : 'Envio de WhatsApp registrado.',
            'activity_id' => $activity->id,
        ], 201);
    }
}
