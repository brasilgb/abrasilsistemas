<?php

namespace App\Http\Controllers;

use App\Http\Requests\Leads\MarkLeadWhatsappReadRequest;
use App\Models\Lead;
use Illuminate\Http\JsonResponse;

class LeadWhatsappConversationController extends Controller
{
    public const MESSAGES_LIMIT = 100;

    /**
     * Últimas mensagens da conversa do lead, em ordem cronológica. Sem efeito colateral:
     * a leitura interna (read_at) só muda pelo markRead, com o que a tela de fato exibiu.
     */
    public function index(Lead $lead): JsonResponse
    {
        $messages = $lead->activities()
            ->conversation()
            ->latest()
            ->latest('id')
            ->limit(self::MESSAGES_LIMIT)
            ->get(['id', 'direction', 'description', 'created_at', 'message_status', 'read_at'])
            ->reverse()
            ->values();

        return response()->json(['messages' => $messages]);
    }

    /**
     * Marca como lidas as mensagens recebidas exibidas na conversa (id <= up_to_id).
     * Idempotente e restrito ao lead da rota; message_status (ACK) nunca é tocado.
     */
    public function markRead(MarkLeadWhatsappReadRequest $request, Lead $lead): JsonResponse
    {
        $marked = $lead->unreadWhatsappMessages()
            ->where('id', '<=', $request->integer('up_to_id'))
            ->update(['read_at' => now()]);

        return response()->json(['marked' => $marked]);
    }
}
