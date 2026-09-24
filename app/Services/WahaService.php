<?php

namespace App\Services;

use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Adapter fino sobre a REST API do WAHA, usado só no backend.
 *
 * Não conhece empresa nem tenant: recebe o nome da sessão de quem chama
 * (hoje CompanyWhatsappConnection, que lê a sessão das configurações).
 * A X-Api-Key sai de config('services.waha.api_key') e nunca é logada nem devolvida.
 */
class WahaService
{
    private const PNG_SIGNATURE = "\x89PNG\r\n\x1a\n";

    /**
     * GET /api/sessions/{session}: status, me.id, me.pushName, me.lid.
     *
     * @return array<string, mixed>
     *
     * @throws WahaException
     */
    public function getSession(string $session): array
    {
        $response = $this->send($session, 'get_session', fn (PendingRequest $client) => $client
            ->acceptJson()
            ->get($this->sessionPath($session)));

        if ($response->status() === 404) {
            throw new WahaException("A sessão {$session} não existe no WAHA. Crie a sessão no WAHA ou corrija o nome nas configurações.", 404);
        }

        return $this->json($session, 'get_session', $response);
    }

    /**
     * POST /api/sessions/{session}/restart: reinicia (ou inicia) a sessão existente, mantendo
     * a configuração dela no WAHA (webhooks inclusos). Não cria nem exclui sessão.
     *
     * @return array<string, mixed>
     *
     * @throws WahaException
     */
    public function restartSession(string $session): array
    {
        $response = $this->send($session, 'restart', fn (PendingRequest $client) => $client
            ->acceptJson()
            ->post($this->sessionPath($session).'/restart'));

        return $this->json($session, 'restart', $response);
    }

    /**
     * POST /api/sessions/{session}/logout: desconecta a conta do WhatsApp sem excluir a sessão.
     *
     * @throws WahaException
     */
    public function logoutSession(string $session): void
    {
        $response = $this->send($session, 'logout', fn (PendingRequest $client) => $client
            ->acceptJson()
            ->post($this->sessionPath($session).'/logout'));

        $this->ensureSuccessful($session, 'logout', $response);
    }

    /**
     * GET /api/{session}/auth/qr: o WAHA responde com a imagem PNG crua (não é JSON).
     *
     * @return string bytes do PNG
     *
     * @throws WahaException
     */
    public function getQrCode(string $session): string
    {
        $response = $this->send($session, 'qr', fn (PendingRequest $client) => $client
            ->accept('image/png')
            ->get('/api/'.rawurlencode($session).'/auth/qr', ['format' => 'image']));

        $this->ensureSuccessful($session, 'qr', $response);

        $body = $response->body();

        // Algumas versões do WAHA devolvem {"mimetype":"image/png","data":"<base64>"}.
        if (str_contains((string) $response->header('Content-Type'), 'application/json')) {
            $data = $response->json('data');
            $body = is_string($data) ? (string) base64_decode($data, true) : '';
        }

        if (! str_starts_with($body, self::PNG_SIGNATURE)) {
            Log::warning('WAHA: QR Code em formato inesperado.', [
                'session' => $session,
                'content_type' => $response->header('Content-Type'),
                'bytes' => strlen($body),
            ]);

            throw new WahaException('O WAHA não devolveu um QR Code válido. Gere um novo QR Code.');
        }

        return $body;
    }

    /**
     * @param  Closure(PendingRequest): Response  $call
     *
     * @throws WahaException
     */
    private function send(string $session, string $operation, Closure $call): Response
    {
        try {
            return $call($this->client());
        } catch (ConnectionException $exception) {
            Log::warning('WAHA: falha de comunicação.', [
                'session' => $session,
                'operation' => $operation,
                'timed_out' => str_contains(strtolower($exception->getMessage()), 'timed out'),
                'error' => mb_substr($exception->getMessage(), 0, 300),
            ]);

            throw WahaException::unavailable();
        }
    }

    /** @throws WahaException */
    private function client(): PendingRequest
    {
        $baseUrl = config('services.waha.base_url');

        if (blank($baseUrl)) {
            Log::error('WAHA: WAHA_BASE_URL não configurada.');

            throw new WahaException('A integração com o WAHA não está configurada. Avise o administrador do sistema.', 503);
        }

        $timeout = (int) config('services.waha.timeout');
        $client = Http::baseUrl(rtrim((string) $baseUrl, '/'))
            ->connectTimeout(5)
            ->timeout($timeout > 0 ? $timeout : 15);

        $apiKey = config('services.waha.api_key');

        return filled($apiKey) ? $client->withHeaders(['X-Api-Key' => $apiKey]) : $client;
    }

    private function sessionPath(string $session): string
    {
        return '/api/sessions/'.rawurlencode($session);
    }

    /**
     * @return array<string, mixed>
     *
     * @throws WahaException
     */
    private function json(string $session, string $operation, Response $response): array
    {
        $this->ensureSuccessful($session, $operation, $response);

        $data = $response->json();

        return is_array($data) ? $data : [];
    }

    /** @throws WahaException */
    private function ensureSuccessful(string $session, string $operation, Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        Log::warning('WAHA respondeu com erro HTTP.', [
            'session' => $session,
            'operation' => $operation,
            'status' => $response->status(),
            'body' => mb_substr($response->body(), 0, 500),
        ]);

        if (in_array($response->status(), [401, 403], true)) {
            throw new WahaException('O WAHA recusou a autenticação do CRM. Avise o administrador do sistema.');
        }

        throw WahaException::unavailable();
    }
}
