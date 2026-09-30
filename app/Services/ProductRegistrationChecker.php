<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\ProspectProduct;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Verificação de cadastro pela API do produto (CRM-WA-29). Contrato: POST JSON
 * {"lookup_field": "whatsapp"|"email", "lookup_value": "..."} com "Authorization: Bearer <token>";
 * resposta HTTP 200 com {"registered": false} ou {"registered": true, "registered_at": ISO 8601
 * com fuso}. Qualquer outra coisa é erro, e erro nunca vale como "não cadastrado" (fail-closed).
 * Cadastro confirmado inicia o trial pela regra de sempre (Lead::startTrial), uma única vez.
 */
class ProductRegistrationChecker
{
    /** Tolerância de relógio para registered_at no futuro; além disso a resposta é recusada. */
    private const CLOCK_SKEW_MINUTES = 5;

    public function __construct(private RegistrationApiUrl $urls) {}

    /**
     * Consulta a API e grava o resultado no lead (registration_*); cadastro confirmado inicia o
     * trial. Nada muda no estado comercial em not_registered ou error. Lead fora da verificação
     * (manual, encerrado, teste já iniciado, produto inativo ou sem prazo) não é consultado: null.
     */
    public function checkAndRecord(Lead $lead): ?RegistrationCheckResult
    {
        if (! $lead->awaitsRegistrationCheck()) {
            return null;
        }

        $result = $this->check($lead, $lead->prospectProduct);

        $checked = [
            'registration_checked_at' => now()->startOfSecond(),
            'registration_check_status' => $result->status,
            'registration_check_error' => $result->error,
        ];

        // UPDATE direto: não é edição do lead (sem updated_at, sem os eventos do model).
        Lead::query()->whereKey($lead->getKey())->toBase()->update($checked);
        $lead->forceFill($checked)->syncOriginalAttributes(array_keys($checked));

        if (! $result->isRegistered()) {
            return $result;
        }

        return $result->withTrialStarted($lead->startTrial(null, $result->registeredAt));
    }

    /** Só a consulta: nunca altera o lead. */
    public function check(Lead $lead, ProspectProduct $product): RegistrationCheckResult
    {
        if (! $product->hasRegistrationApiConfigured()) {
            return RegistrationCheckResult::error('not_configured');
        }

        $value = self::lookupValue($lead, (string) $product->registration_lookup_field);

        if ($value === null) {
            return RegistrationCheckResult::error('missing_identifier');
        }

        $url = trim((string) $product->registration_api_url);
        $address = $this->urls->pinnedAddress($url);

        if ($address === null) {
            return $this->fail($lead, $product, RegistrationCheckResult::error('blocked_url'));
        }

        $timeout = (int) config('services.prospect_registration_check.timeout');

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->withToken((string) $product->registration_api_token)
                ->withoutRedirecting()
                ->connectTimeout(5)
                ->timeout($timeout > 0 ? $timeout : 10)
                // Conecta no IP conferido, sem nova resolução DNS (a URL já é https://host:443).
                ->withOptions(['curl' => [CURLOPT_RESOLVE => [RegistrationApiUrl::host($url).':443:'.$address]]])
                ->post($url, [
                    'lookup_field' => $product->registration_lookup_field,
                    'lookup_value' => $value,
                ]);
        } catch (ConnectionException $exception) {
            $timedOut = str_contains(strtolower($exception->getMessage()), 'timed out');

            return $this->fail($lead, $product, RegistrationCheckResult::error($timedOut ? 'timeout' : 'connection_failed'));
        } catch (Throwable $exception) {
            return $this->fail($lead, $product, RegistrationCheckResult::error('connection_failed'), $exception::class);
        }

        $result = self::interpret($response);

        return $result->status === 'error' ? $this->fail($lead, $product, $result) : $result;
    }

    /** Valor enviado à API: WhatsApp normalizado (55 + DDD + número) ou e-mail em minúsculas. */
    public static function lookupValue(Lead $lead, string $field): ?string
    {
        if ($field === 'whatsapp') {
            return LeadWhatsappService::normalizeNumber($lead->whatsapp);
        }

        if ($field === 'email') {
            $email = mb_strtolower(trim((string) $lead->email));

            return filter_var($email, FILTER_VALIDATE_EMAIL) === false ? null : $email;
        }

        return null;
    }

    public static function interpret(Response $response): RegistrationCheckResult
    {
        $status = $response->status();

        if ($status !== 200) {
            return RegistrationCheckResult::error(match (true) {
                $status >= 300 && $status < 400 => 'redirect',
                $status === 401, $status === 403 => 'unauthorized',
                $status === 404 => 'not_found',
                $status === 422 => 'unprocessable',
                $status === 429 => 'rate_limited',
                $status >= 500 => 'server_error',
                default => 'unexpected_status',
            }, $status);
        }

        $data = json_decode($response->body(), true);

        if (! is_array($data)) {
            return RegistrationCheckResult::error(json_last_error() === JSON_ERROR_NONE ? 'invalid_response' : 'invalid_json', $status);
        }

        if (($data['registered'] ?? null) === false) {
            return RegistrationCheckResult::notRegistered();
        }

        if (($data['registered'] ?? null) !== true) {
            return RegistrationCheckResult::error('invalid_response', $status);
        }

        $registeredAt = self::parseRegisteredAt($data['registered_at'] ?? null);

        return $registeredAt === null
            ? RegistrationCheckResult::error('invalid_registered_at', $status)
            : RegistrationCheckResult::registered($registeredAt);
    }

    /** ISO 8601 com fuso explícito e não no futuro (com a tolerância de relógio). */
    private static function parseRegisteredAt(mixed $value): ?CarbonInterface
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2}(\.\d+)?)?(Z|[+-]\d{2}:?\d{2})$/i', trim($value)) !== 1) {
            return null;
        }

        try {
            $date = Carbon::parse(trim($value));
        } catch (Throwable) {
            return null;
        }

        // Data inexistente (31/02) seria "corrigida" pelo parse para outro dia: recusada.
        if ($date->format('Y-m-d H:i') !== str_replace('T', ' ', substr(trim($value), 0, 16))) {
            return null;
        }

        $date = $date->setTimezone(config('app.timezone'));

        return $date->year < 2000 || $date->gt(now()->addMinutes(self::CLOCK_SKEW_MINUTES)) ? null : $date;
    }

    /** Log sem segredo: nem token, nem URL completa (pode ter path secreto), nem o identificador. */
    private function fail(Lead $lead, ProspectProduct $product, RegistrationCheckResult $result, ?string $exception = null): RegistrationCheckResult
    {
        Log::warning('Verificação de cadastro: consulta à API do produto falhou.', array_filter([
            'lead_id' => $lead->id,
            'prospect_product_id' => $product->id,
            'host' => RegistrationApiUrl::host((string) $product->registration_api_url),
            'error' => $result->error,
            'http_status' => $result->httpStatus,
            // Só a classe: a mensagem da exceção pode trazer a URL completa.
            'exception' => $exception,
        ], fn ($value) => $value !== null));

        return $result;
    }
}
