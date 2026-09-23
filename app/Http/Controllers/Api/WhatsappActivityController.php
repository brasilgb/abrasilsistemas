<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LogWhatsappActivityRequest;
use App\Models\Lead;
use Illuminate\Http\JsonResponse;

class WhatsappActivityController extends Controller
{
    public function store(LogWhatsappActivityRequest $request, Lead $lead): JsonResponse
    {
        $data = $request->validated();
        $messageId = $data['message_id'] ?? null;

        if ($messageId !== null) {
            $existing = $lead->activities()
                ->where('provider_message_id', $messageId)
                ->first();

            if ($existing !== null) {
                return response()->json([
                    'success' => true,
                    'message' => 'Envio de WhatsApp já estava registrado.',
                    'activity_id' => $existing->id,
                    'idempotent' => true,
                ]);
            }
        }

        $activity = $lead->activities()->create([
            'user_id' => null,
            'type' => 'whatsapp',
            'status' => null,
            'contacted_at' => now(),
            'description' => $data['message'],
            'provider_message_id' => $messageId,
            'message_status' => $data['status'],
        ]);

        $lead->forceFill(['last_contacted_at' => $activity->contacted_at])->save();

        return response()->json([
            'success' => true,
            'message' => 'Envio de WhatsApp registrado.',
            'activity_id' => $activity->id,
        ], 201);
    }
}
