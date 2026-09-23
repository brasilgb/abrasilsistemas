<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdateWhatsappMessageStatusRequest;
use App\Models\LeadActivity;
use Illuminate\Http\JsonResponse;

class WhatsappMessageStatusController extends Controller
{
    public function update(UpdateWhatsappMessageStatusRequest $request, string $providerMessageId): JsonResponse
    {
        $status = $request->validated('status');

        $activity = LeadActivity::query()
            ->where('type', 'whatsapp')
            ->where('provider_message_id', $providerMessageId)
            ->latest('id')
            ->first();

        if ($activity === null) {
            return response()->json([
                'success' => false,
                'message' => 'Mensagem WhatsApp não encontrada.',
            ], 404);
        }

        if ($activity->message_status === $status) {
            return response()->json([
                'success' => true,
                'message' => 'Status já estava atualizado.',
                'activity_id' => $activity->id,
                'message_status' => $activity->message_status,
                'idempotent' => true,
            ]);
        }

        if (! $activity->canTransitionMessageStatusTo($status)) {
            return response()->json([
                'success' => true,
                'message' => 'Status ignorado para evitar regressão.',
                'activity_id' => $activity->id,
                'message_status' => $activity->message_status,
                'ignored' => true,
            ]);
        }

        $activity->forceFill(['message_status' => $status])->save();

        return response()->json([
            'success' => true,
            'message' => 'Status da mensagem atualizado.',
            'activity_id' => $activity->id,
            'message_status' => $activity->message_status,
        ]);
    }
}
