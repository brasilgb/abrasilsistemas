<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WhatsappInbox;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Recebe os webhooks do WAHA (message, message.any, message.ack) para o atendimento.
 *
 * Autenticação: o WAHA assina o corpo com HMAC-SHA512 (config "hmac.key" do webhook da sessão)
 * no header X-Webhook-Hmac. Sem WAHA_WEBHOOK_HMAC_KEY no ambiente, o endpoint fica desligado.
 * Eventos ignorados (outra sessão, grupo, duplicado) respondem 200 para o WAHA não reenviar.
 */
class WahaWebhookController extends Controller
{
    public function __invoke(Request $request, WhatsappInbox $inbox): JsonResponse
    {
        $key = (string) config('services.waha.webhook_hmac_key');

        if ($key === '') {
            return response()->json(['success' => false, 'message' => 'Webhook do atendimento desativado.'], 503);
        }

        $signature = strtolower((string) $request->header('X-Webhook-Hmac'));

        if ($signature === '' || ! hash_equals(hash_hmac('sha512', $request->getContent(), $key), $signature)) {
            Log::warning('Webhook WAHA recusado: assinatura HMAC inválida.', ['ip' => $request->ip()]);

            return response()->json(['success' => false, 'message' => 'Assinatura inválida.'], 401);
        }

        $event = $request->json()->all();
        $ignored = $inbox->handleEvent($event);

        return response()->json([
            'success' => $ignored === null,
            'event' => $event['event'] ?? null,
            'reason' => $ignored,
        ]);
    }
}
