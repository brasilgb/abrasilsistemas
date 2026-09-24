<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\CompanyWhatsappConnection;
use App\Services\WahaException;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Conexão da sessão WAHA do WhatsApp da empresa (Configurações → Leads).
 * Nenhuma rota aceita sessão vinda do navegador: ela sai das configurações salvas.
 */
class CompanyWhatsappConnectionController extends Controller
{
    public function __construct(private readonly CompanyWhatsappConnection $connection) {}

    public function status(): JsonResponse
    {
        return response()->json($this->connection->status());
    }

    public function connect(): JsonResponse
    {
        return $this->respond(fn () => $this->connection->connect());
    }

    public function disconnect(): JsonResponse
    {
        return $this->respond(fn () => $this->connection->disconnect());
    }

    public function qr(): Response|JsonResponse
    {
        try {
            $png = $this->connection->qrCode();
        } catch (WahaException $exception) {
            return response()->json(['message' => $exception->getMessage()], $exception->status);
        }

        return response($png, 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    /**
     * @param  Closure(): array<string, mixed>  $action
     */
    private function respond(Closure $action): JsonResponse
    {
        try {
            return response()->json($action());
        } catch (WahaException $exception) {
            return response()->json(['message' => $exception->getMessage()], $exception->status);
        }
    }
}
