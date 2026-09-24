<?php

namespace App\Services;

/**
 * Conexão real do WhatsApp da empresa (sessão WAHA), gerenciada pelo painel.
 *
 * A sessão vem sempre de CompanyWhatsappSettings, nunca do navegador. O número salvo nas
 * configurações não prova qual conta está conectada: a fonte de verdade é o `me` da sessão
 * no WAHA, e aqui só se compara os dois (nada é corrigido automaticamente).
 */
class CompanyWhatsappConnection
{
    public const STATE_NOT_CONFIGURED = 'not_configured';

    public const STATE_CONNECTED = 'connected';

    public const STATE_QR = 'scan_qr_code';

    public const STATE_CONNECTING = 'connecting';

    public const STATE_FAILED = 'failed';

    public const STATE_DISCONNECTED = 'disconnected';

    public const STATE_UNKNOWN = 'unknown';

    public const STATE_ERROR = 'error';

    /** Status do WAHA => estado e rótulo do painel. Qualquer outro vira STATE_UNKNOWN. */
    private const STATES = [
        'WORKING' => [self::STATE_CONNECTED, 'Conectado'],
        'SCAN_QR_CODE' => [self::STATE_QR, 'Aguardando leitura do QR Code'],
        'STARTING' => [self::STATE_CONNECTING, 'Conectando'],
        'FAILED' => [self::STATE_FAILED, 'Falha na conexão'],
        'STOPPED' => [self::STATE_DISCONNECTED, 'Desconectado'],
    ];

    public function __construct(
        private readonly CompanyWhatsappSettings $settings,
        private readonly WahaService $waha,
    ) {}

    /**
     * Status atual para o painel. Falhas do WAHA viram STATE_ERROR com mensagem amigável.
     *
     * @return array<string, mixed>
     */
    public function status(): array
    {
        $company = $this->settings->current();
        $session = $this->sessionFrom($company);

        if ($session === null) {
            return $this->notConfigured($company);
        }

        try {
            return $this->describe($company, $session, $this->waha->getSession($session));
        } catch (WahaException $exception) {
            return [
                ...$this->base($company, $session),
                'state' => self::STATE_ERROR,
                'label' => 'Erro',
                'message' => $exception->getMessage(),
            ];
        }
    }

    /**
     * Inicia a conexão: só reinicia a sessão quando ela não está conectada nem já
     * aguardando QR/iniciando. Nunca cria nem exclui sessão no WAHA.
     *
     * @return array<string, mixed>
     *
     * @throws WahaException
     */
    public function connect(): array
    {
        [$company, $session] = $this->requireSession();

        $current = $this->waha->getSession($session);

        if (in_array($current['status'] ?? null, ['WORKING', 'SCAN_QR_CODE', 'STARTING'], true)) {
            return $this->describe($company, $session, $current);
        }

        $this->waha->restartSession($session);

        return $this->describe($company, $session, $this->waha->getSession($session));
    }

    /**
     * PNG do QR Code, só enquanto a sessão está aguardando leitura. Não é salvo em lugar nenhum.
     *
     * @throws WahaException
     */
    public function qrCode(): string
    {
        [, $session] = $this->requireSession();

        $status = $this->waha->getSession($session)['status'] ?? null;

        if ($status !== 'SCAN_QR_CODE') {
            throw new WahaException('O QR Code não está disponível: a sessão não está aguardando leitura. Gere um novo QR Code.', 409);
        }

        return $this->waha->getQrCode($session);
    }

    /**
     * Logout da conta na sessão. Mantém a sessão, os webhooks e as configurações da empresa.
     *
     * @return array<string, mixed>
     *
     * @throws WahaException
     */
    public function disconnect(): array
    {
        [$company, $session] = $this->requireSession();

        $this->waha->logoutSession($session);

        return $this->describe($company, $session, $this->waha->getSession($session));
    }

    /**
     * Normaliza um número ou JID do WhatsApp (ex.: 5551998931325@c.us) para só dígitos.
     */
    public static function normalizeJid(?string $jid): ?string
    {
        if (blank($jid)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', explode('@', (string) $jid)[0]) ?? '';

        return $digits !== '' ? $digits : null;
    }

    /**
     * Compara o número configurado com o da conta conectada. Celulares brasileiros antigos
     * aparecem no WhatsApp sem o nono dígito (55 51 98931325), então ele é ignorado na comparação.
     */
    public static function sameNumber(?string $configured, ?string $connected): bool
    {
        $a = self::withoutNinthDigit(self::normalizeJid($configured));
        $b = self::withoutNinthDigit(self::normalizeJid($connected));

        return $a !== null && $a === $b;
    }

    private static function withoutNinthDigit(?string $number): ?string
    {
        if ($number !== null && strlen($number) === 13 && str_starts_with($number, '55') && $number[4] === '9') {
            return substr($number, 0, 4).substr($number, 5);
        }

        return $number;
    }

    /**
     * @param  array{configured: bool, enabled: bool, number: string|null, provider: string|null, session: string|null}  $company
     * @param  array<string, mixed>  $waha
     * @return array<string, mixed>
     */
    private function describe(array $company, string $session, array $waha): array
    {
        $wahaStatus = is_string($waha['status'] ?? null) ? $waha['status'] : null;
        [$state, $label] = self::STATES[$wahaStatus] ?? [self::STATE_UNKNOWN, 'Status desconhecido'];

        $me = is_array($waha['me'] ?? null) ? $waha['me'] : [];
        $account = null;
        $numberMatches = null;

        if ($state === self::STATE_CONNECTED && is_string($me['id'] ?? null)) {
            $account = [
                'id' => $me['id'],
                'number' => self::normalizeJid($me['id']),
                'name' => is_string($me['pushName'] ?? null) ? $me['pushName'] : null,
                'lid' => is_string($me['lid'] ?? null) ? $me['lid'] : null,
            ];
            $numberMatches = self::sameNumber($company['number'], $me['id']);
        }

        return [
            ...$this->base($company, $session),
            'state' => $state,
            'label' => $label,
            'waha_status' => $wahaStatus,
            'account' => $account,
            'number_matches' => $numberMatches,
            'message' => null,
        ];
    }

    /**
     * @param  array{configured: bool, enabled: bool, number: string|null, provider: string|null, session: string|null}  $company
     * @return array<string, mixed>
     */
    private function base(array $company, ?string $session): array
    {
        return [
            'session' => $session,
            'provider' => $company['provider'],
            'configured_number' => $company['number'],
            'waha_status' => null,
            'account' => null,
            'number_matches' => null,
        ];
    }

    /**
     * @param  array{configured: bool, enabled: bool, number: string|null, provider: string|null, session: string|null}  $company
     * @return array<string, mixed>
     */
    private function notConfigured(array $company): array
    {
        return [
            ...$this->base($company, null),
            'state' => self::STATE_NOT_CONFIGURED,
            'label' => 'Sessão não configurada',
            'message' => 'Salve a sessão do WAHA nas configurações antes de conectar.',
        ];
    }

    /**
     * @return array{0: array{configured: bool, enabled: bool, number: string|null, provider: string|null, session: string|null}, 1: string}
     *
     * @throws WahaException
     */
    private function requireSession(): array
    {
        $company = $this->settings->current();
        $session = $this->sessionFrom($company);

        if ($session === null) {
            throw new WahaException('Salve a sessão do WAHA nas configurações antes de conectar.', 422);
        }

        return [$company, $session];
    }

    /**
     * @param  array{configured: bool, enabled: bool, number: string|null, provider: string|null, session: string|null}  $company
     */
    private function sessionFrom(array $company): ?string
    {
        $session = $company['session'];

        if ($company['provider'] !== CompanyWhatsappSettings::PROVIDER_WAHA
            || blank($session)
            || ! preg_match(CompanyWhatsappSettings::SESSION_PATTERN, $session)) {
            return null;
        }

        return $session;
    }
}
