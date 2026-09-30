<?php

namespace App\Services;

/**
 * Regras de endereço da API de cadastro por produto (CRM-WA-29), contra SSRF: só HTTPS na porta
 * 443, sem usuário/senha na URL, sem nomes internos e sem IP literal que não seja público. Na
 * hora da chamada o nome é resolvido e TODOS os IPv4 precisam ser públicos; a conexão é presa
 * ao IP conferido (sem nova resolução entre a checagem e a chamada) e redirects não são seguidos.
 */
class RegistrationApiUrl
{
    private const INTERNAL_SUFFIXES = ['.localhost', '.local', '.internal', '.lan', '.home.arpa'];

    public function __construct(private HostResolver $resolver) {}

    /** Motivo da recusa do formato da URL, ou null quando pode ser salva. */
    public static function formatError(?string $url): ?string
    {
        $url = trim((string) $url);
        $parts = parse_url($url);

        if ($url === '' || $parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return 'Informe uma URL completa, começando com https://.';
        }

        if (strtolower($parts['scheme']) !== 'https') {
            return 'A URL da API precisa usar HTTPS.';
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            return 'Não coloque usuário ou senha na URL; use o token.';
        }

        if (isset($parts['port']) && $parts['port'] !== 443) {
            return 'A URL da API precisa usar a porta padrão do HTTPS (443).';
        }

        if (isset($parts['fragment'])) {
            return 'A URL da API não pode ter fragmento (#).';
        }

        $host = strtolower(rtrim($parts['host'], '.'));

        if (str_starts_with($host, '[')) {
            return 'Use um nome de domínio ou um IPv4 público.';
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return self::isPublicIp($host) ? null : 'A URL da API não pode apontar para um endereço interno.';
        }

        if ($host === 'localhost' || ! str_contains($host, '.') || self::hasInternalSuffix($host)) {
            return 'A URL da API não pode apontar para um endereço interno.';
        }

        return null;
    }

    /**
     * IPv4 público para prender a conexão, ou null quando a URL é recusada (formato, nome que
     * não resolve ou algum endereço interno/reservado entre os resolvidos).
     */
    public function pinnedAddress(string $url): ?string
    {
        if (self::formatError($url) !== null) {
            return null;
        }

        $addresses = $this->resolver->ipv4(self::host($url));

        foreach ($addresses as $address) {
            if (! self::isPublicIp($address)) {
                return null;
            }
        }

        return $addresses[0] ?? null;
    }

    public static function host(string $url): string
    {
        return strtolower(rtrim((string) parse_url(trim($url), PHP_URL_HOST), '.'));
    }

    private static function isPublicIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false
            // Faixas que o PHP não marca como reservadas: CGNAT 100.64/10 e benchmark 198.18/15.
            && ! self::inRange($ip, '100.64.0.0', 10)
            && ! self::inRange($ip, '198.18.0.0', 15);
    }

    private static function inRange(string $ip, string $network, int $bits): bool
    {
        $mask = -1 << (32 - $bits);

        return (ip2long($ip) & $mask) === (ip2long($network) & $mask);
    }

    private static function hasInternalSuffix(string $host): bool
    {
        foreach (self::INTERNAL_SUFFIXES as $suffix) {
            if (str_ends_with($host, $suffix)) {
                return true;
            }
        }

        return false;
    }
}
