<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreWhatsappExternalOutboundRequest;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Services\CompanyWhatsappConnection;
use App\Services\CompanyWhatsappSettings;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class WhatsappExternalOutboundController extends Controller
{
    /**
     * Registra uma mensagem de texto enviada pela conta da empresa fora do CRM (celular ou
     * WhatsApp Web), repassada pelo n8n a partir do message.any com fromMe=true. O lead é
     * localizado pelo destinatário; nunca é criado.
     *
     * As mensagens enviadas pelo CRM também geram message.any fromMe: elas já foram (ou serão)
     * gravadas pelo /log com o mesmo id, e o lock compartilhado garante uma única atividade.
     */
    public function store(StoreWhatsappExternalOutboundRequest $request, CompanyWhatsappSettings $companyWhatsapp): JsonResponse
    {
        $data = $request->validated();
        $company = $companyWhatsapp->current();

        // Diferente do inbound, exige sessão configurada: toda mensagem enviada por qualquer
        // sessão do WAHA passaria por aqui.
        if ($company['session'] === null) {
            return $this->ignored('session_not_configured', 'Nenhuma sessão configurada para o WhatsApp da empresa.');
        }

        if ($company['session'] !== $data['session']) {
            return $this->ignored('session_mismatch', 'Mensagem de uma sessão diferente da configurada para o WhatsApp da empresa.');
        }

        $recipient = $request->recipientNumber();

        if (CompanyWhatsappConnection::sameNumber($company['number'], $recipient)) {
            return $this->ignored('self_chat', 'Mensagem enviada para o próprio número da empresa.');
        }

        try {
            return LeadActivity::whatsappMessageLock($data['provider_message_id'])
                ->block(5, fn () => $this->record($data, $recipient));
        } catch (LockTimeoutException) {
            return response()->json([
                'success' => false,
                'reason' => 'processing',
                'message' => 'A mensagem ainda está sendo processada. Tente novamente.',
            ], 409);
        }
    }

    /**
     * @param  array{session: string, provider_message_id: string, to: string, message: string, status?: string|null, timestamp?: int|null}  $data
     */
    private function record(array $data, string $recipient): JsonResponse
    {
        // Busca global: o /log grava o envio do CRM no lead escolhido pelo operador, que pode não
        // ser o mesmo que o número resolveria.
        $existing = LeadActivity::findWhatsappMessage($data['provider_message_id']);

        if ($existing !== null) {
            return response()->json([
                'success' => true,
                'reason' => 'already_logged',
                'message' => 'Mensagem enviada já estava registrada.',
                'activity_id' => $existing->id,
                'lead_id' => $existing->lead_id,
                'idempotent' => true,
            ]);
        }

        $lead = Lead::findByWhatsappNumber($recipient);

        if ($lead === null) {
            Log::info('WhatsApp enviado fora do CRM: nenhum lead com o número do destinatário.', [
                'session' => $data['session'],
                'provider_message_id' => $data['provider_message_id'],
            ]);

            return $this->ignored('lead_not_found', 'Nenhum lead cadastrado com o número do destinatário.');
        }

        $status = strtoupper(trim((string) ($data['status'] ?? '')));

        $activity = $lead->activities()->create([
            'user_id' => null,
            'type' => 'whatsapp',
            'direction' => LeadActivity::DIRECTION_OUTBOUND,
            'status' => null,
            'contacted_at' => now(),
            'description' => $data['message'],
            'provider_message_id' => $data['provider_message_id'],
            // O message.any só é emitido depois que a mensagem passou pelo servidor.
            'message_status' => in_array($status, LeadActivity::MESSAGE_STATUSES, true)
                ? $status
                : LeadActivity::MESSAGE_STATUS_SERVER,
        ]);

        $lead->forceFill(['last_contacted_at' => $activity->contacted_at])->save();

        return response()->json([
            'success' => true,
            'message' => 'Mensagem enviada fora do CRM registrada.',
            'activity_id' => $activity->id,
            'lead_id' => $lead->id,
        ], 201);
    }

    private function ignored(string $reason, string $message): JsonResponse
    {
        return response()->json([
            'success' => false,
            'reason' => $reason,
            'message' => $message,
        ]);
    }
}
