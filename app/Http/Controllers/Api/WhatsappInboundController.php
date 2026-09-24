<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreWhatsappInboundRequest;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Services\CompanyWhatsappSettings;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class WhatsappInboundController extends Controller
{
    /**
     * Registra uma mensagem de texto recebida (cliente → empresa), enviada pelo n8n a partir do
     * evento message.any do WAHA. O lead é localizado pelo telefone do remetente; nunca é criado.
     *
     * Remetente sem lead ou sessão de outra conta são resultados esperados, não falhas: respondem
     * 200 com success=false para o n8n não tratar como erro nem repetir a chamada.
     */
    public function store(StoreWhatsappInboundRequest $request, CompanyWhatsappSettings $companyWhatsapp): JsonResponse
    {
        $data = $request->validated();
        $companySession = $companyWhatsapp->current()['session'];

        // O mesmo WAHA atende outras sessões; com sessão configurada, só ela alimenta o CRM.
        if ($companySession !== null && $companySession !== $data['session']) {
            return $this->ignored('session_mismatch', 'Mensagem de uma sessão diferente da configurada para o WhatsApp da empresa.');
        }

        // O WAHA emite message e message.any para a mesma mensagem: o lock evita a gravação dupla
        // quando as duas chegam ao mesmo tempo, já que provider_message_id não é UNIQUE no banco.
        try {
            return Cache::lock('whatsapp-inbound:'.sha1($data['provider_message_id']), 10)
                ->block(5, fn () => $this->record($data, $request->senderNumber()));
        } catch (LockTimeoutException) {
            return response()->json([
                'success' => false,
                'reason' => 'processing',
                'message' => 'A mensagem ainda está sendo processada. Tente novamente.',
            ], 409);
        }
    }

    /**
     * @param  array{session: string, provider_message_id: string, from: string, push_name?: string|null, message: string}  $data
     */
    private function record(array $data, string $sender): JsonResponse
    {
        // Busca global: um fromMe repassado por engano tem o id da mensagem outbound já registrada.
        $existing = LeadActivity::query()
            ->where('type', 'whatsapp')
            ->where('provider_message_id', $data['provider_message_id'])
            ->first();

        if ($existing !== null) {
            return response()->json([
                'success' => true,
                'message' => 'Mensagem recebida já estava registrada.',
                'activity_id' => $existing->id,
                'lead_id' => $existing->lead_id,
                'idempotent' => true,
            ]);
        }

        $lead = Lead::findByWhatsappNumber($sender);

        if ($lead === null) {
            Log::info('WhatsApp recebido: nenhum lead com o número do remetente.', [
                'session' => $data['session'],
                'provider_message_id' => $data['provider_message_id'],
            ]);

            return $this->ignored('lead_not_found', 'Nenhum lead cadastrado com o número do remetente.');
        }

        $activity = $lead->activities()->create([
            'user_id' => null,
            'type' => 'whatsapp',
            'direction' => LeadActivity::DIRECTION_INBOUND,
            'status' => null,
            'contacted_at' => now(),
            'description' => $data['message'],
            'provider_message_id' => $data['provider_message_id'],
            'message_status' => null,
        ]);

        $lead->forceFill(['last_contacted_at' => $activity->contacted_at])->save();

        return response()->json([
            'success' => true,
            'message' => 'Mensagem recebida registrada.',
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
