<?php

namespace App\Services\Seo;

use Illuminate\Http\Request;

/**
 * robots.txt gerado a partir das configurações. As áreas privadas são sempre bloqueadas
 * (e continuam protegidas por autenticação: robots.txt é só uma orientação aos robôs).
 * O painel só permite acrescentar caminhos Disallow validados; não aceita diretivas
 * livres nem "Disallow: /", que tiraria o site inteiro dos buscadores. Para bloquear tudo
 * existe a opção explícita "bloquear indexação".
 */
class RobotsBuilder
{
    /** Caminhos privados, sempre bloqueados. */
    public const PRIVATE_PATHS = [
        '/admin', '/atendimento', '/solicitacoes', '/users', '/testimonials', '/portfolio',
        '/settings', '/login', '/register', '/forgot-password', '/reset-password',
        '/two-factor-challenge', '/user', '/email', '/area-restrita', '/api/', '/webhooks/',
    ];

    /** Caminho aceito no Disallow: começa com /, sem espaços, sem quebra de linha, até 200 caracteres. */
    public const PATH_PATTERN = '#^/[A-Za-z0-9._~\-/*$?=&%]{1,199}$#';

    public function __construct(private SeoSettings $settings) {}

    public function build(?Request $request = null): string
    {
        $lines = ['User-agent: *'];

        if (! $this->settings->indexingAllowed($request)) {
            $lines[] = 'Disallow: /';
        } else {
            $paths = array_unique([...self::PRIVATE_PATHS, ...self::validPaths((array) $this->settings->get('robots_disallow'))]);

            foreach ($paths as $path) {
                $lines[] = 'Disallow: '.$path;
            }

            $lines[] = '';
            $lines[] = 'Sitemap: '.$this->settings->url('/sitemap.xml');
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * @param  array<int, mixed>  $paths
     * @return list<string>
     */
    public static function validPaths(array $paths): array
    {
        return array_values(array_filter(array_map(fn ($path) => trim((string) $path), $paths), fn (string $path) => self::isValidPath($path)));
    }

    public static function isValidPath(string $path): bool
    {
        return $path !== '/' && $path !== '/*' && preg_match(self::PATH_PATTERN, $path) === 1;
    }
}
