<?php

namespace App\Services;

use App\Models\Lead;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Envio manual de WhatsApp: CRM -> n8n -> WAHA.
 *
 * Quem registra a LeadActivity é o próprio workflow do n8n, pelo endpoint
 * POST /api/prospects/{lead}/whatsapp/log; este serviço não grava histórico.
 *
 * O remetente (WhatsApp da empresa, provedor e sessão WAHA) vem de CompanyWhatsappSettings;
 * o destinatário é sempre o WhatsApp do Lead.
 */
class LeadWhatsappService
{
    public function __construct(private readonly CompanyWhatsappSettings $companyWhatsapp) {}

    /**
     * Normaliza o WhatsApp do Lead para o formato usado pelo WAHA (55 + DDD + número),
     * seguindo a mesma regra do link wa.me da listagem de leads: só dígitos e prefixo 55.
     * Retorna null quando o número não pode ser um celular/fixo brasileiro válido.
     */
    public static function normalizeNumber(?string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value) ?? '';

        // DDD + número (10 ou 11 dígitos): acrescenta o código do país.
        if (strlen($digits) === 10 || strlen($digits) === 11) {
            return '55'.$digits;
        }

        if ((strlen($digits) === 12 || strlen($digits) === 13) && str_starts_with($digits, '55')) {
            return $digits;
        }

        return null;
    }

    /**
     * @return array{message_id: string|null, status: string|null, activity_id: int|null}
     *
     * @throws LeadWhatsappException
     */
    public function send(Lead $lead, string $message): array
    {
        if (blank($lead->whatsapp)) {
            throw new LeadWhatsappException('Este prospect não possui WhatsApp cadastrado.');
        }

        $number = self::normalizeNumber($lead->whatsapp);

        if ($number === null) {
            throw new LeadWhatsappException('O WhatsApp cadastrado neste prospect não é um número válido.');
        }

        $sender = $this->sender($lead);

        $url = config('services.n8n.whatsapp_webhook_url');

        if (blank($url)) {
            Log::error('Envio de WhatsApp: N8N_WHATSAPP_WEBHOOK_URL não configurada.', ['lead_id' => $lead->id]);

            throw new LeadWhatsappException('O envio de WhatsApp não está configurado. Avise o administrador do sistema.');
        }

        $header = config('services.n8n.whatsapp_webhook_header');
        $token = config('services.n8n.whatsapp_webhook_token');

        if (blank($header) || blank($token)) {
            // Só informa o que falta; o valor do token nunca vai para o log.
            Log::error('Envio de WhatsApp: autenticação do webhook do n8n não configurada.', [
                'lead_id' => $lead->id,
                'missing' => blank($header) ? 'N8N_WHATSAPP_WEBHOOK_HEADER' : 'N8N_WHATSAPP_WEBHOOK_TOKEN',
            ]);

            throw new LeadWhatsappException('O envio de WhatsApp não está configurado. Avise o administrador do sistema.');
        }

        $timeout = (int) config('services.n8n.whatsapp_webhook_timeout');

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->withHeaders([$header => $token])
                ->connectTimeout(5)
                ->timeout($timeout > 0 ? $timeout : 20)
                ->post($url, [
                    'prospect_id' => $lead->id,
                    'nome' => $lead->company_name,
                    'whatsapp' => $number,
                    'mensagem' => $message,
                    ...$sender,
                ]);
        } catch (ConnectionException $exception) {
            // Timeout não garante que a mensagem deixou de ser enviada: o n8n pode ter concluído depois.
            $timedOut = str_contains(strtolower($exception->getMessage()), 'timed out');

            Log::warning('Envio de WhatsApp: falha de conexão com o n8n.', [
                'lead_id' => $lead->id,
                'timed_out' => $timedOut,
                // A URL do webhook pode conter um path secreto; não vai para o log.
                'error' => str_replace($url, '[n8n-webhook]', $exception->getMessage()),
            ]);

            throw new LeadWhatsappException($timedOut
                ? 'O serviço de WhatsApp demorou para responder. Confira o histórico antes de reenviar para evitar mensagem duplicada.'
                : 'Não foi possível conectar ao serviço de WhatsApp. Tente novamente em instantes.');
        }

        return $this->handleResponse($lead, $response);
    }

    /**
     * Campos do remetente acrescentados ao payload do n8n. Sem configuração salva no painel,
     * o payload segue o contrato anterior e o n8n usa a sessão que ele mesmo define.
     *
     * @return array{provider?: string, session?: string, remetente_whatsapp?: string}
     *
     * @throws LeadWhatsappException
     */
    private function sender(Lead $lead): array
    {
        $company = $this->companyWhatsapp->current();

        if (! $company['configured']) {
            return [];
        }

        if (! $company['enabled']) {
            throw new LeadWhatsappException('O envio de WhatsApp está desativado nas configurações da empresa.');
        }

        if (blank($company['session']) || blank($company['number']) || blank($company['provider'])) {
            Log::error('Envio de WhatsApp: WhatsApp da empresa habilitado sem número, provedor ou sessão.', ['lead_id' => $lead->id]);

            throw new LeadWhatsappException('O envio de WhatsApp não está configurado. Avise o administrador do sistema.');
        }

        return [
            'provider' => $company['provider'],
            'session' => $company['session'],
            'remetente_whatsapp' => $company['number'],
        ];
    }

    /**
     * @return array{message_id: string|null, status: string|null, activity_id: int|null}
     */
    private function handleResponse(Lead $lead, Response $response): array
    {
        if ($response->failed()) {
            Log::warning('Envio de WhatsApp: n8n respondeu com erro HTTP.', [
                'lead_id' => $lead->id,
                'status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 500),
            ]);

            throw new LeadWhatsappException('O serviço de WhatsApp recusou o envio. Tente novamente em instantes.');
        }

        $data = $response->json();

        if (! is_array($data) || ! array_key_exists('success', $data)) {
            Log::warning('Envio de WhatsApp: resposta inválida do n8n.', [
                'lead_id' => $lead->id,
                'status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 500),
            ]);

            throw new LeadWhatsappException('O serviço de WhatsApp retornou uma resposta inesperada. Confira o histórico antes de reenviar.');
        }

        if ($data['success'] !== true) {
            Log::warning('Envio de WhatsApp: n8n retornou success=false.', [
                'lead_id' => $lead->id,
                'message' => is_string($data['message'] ?? null) ? mb_substr($data['message'], 0, 500) : null,
            ]);

            throw new LeadWhatsappException('A mensagem não pôde ser enviada pelo WhatsApp. Verifique o número e tente novamente.');
        }

        return [
            'message_id' => is_string($data['message_id'] ?? null) ? $data['message_id'] : null,
            'status' => is_string($data['status'] ?? null) ? $data['status'] : null,
            'activity_id' => is_numeric($data['activity_id'] ?? null) ? (int) $data['activity_id'] : null,
        ];
    }
}
