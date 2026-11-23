<?php

namespace App\Services;

use App\Models\Lead;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Origem comercial da visita: parâmetros UTM, site de indicação e página de entrada, guardados na
 * sessão do servidor. Vale a primeira origem identificada (campanha ou site de indicação), por inteiro:
 * uma campanha posterior não se mistura com a indicação que trouxe a pessoa. Nada é deduzido: sem dado,
 * fica em branco. A página de entrada é sempre a da primeira visita da sessão.
 */
class VisitOrigin
{
    public const SESSION_KEY = 'visit_origin';

    public const UTM_FIELDS = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];

    /** Todos os campos gravados na solicitação (colunas de `leads`). */
    public const FIELDS = ['entry_page', 'referrer_host', ...self::UTM_FIELDS];

    /** Grupos de origem do filtro de Solicitações (os mesmos critérios de label()). */
    public const GROUPS = ['campanha' => 'Campanhas (todas)', 'busca' => 'Busca orgânica', 'indicacao' => 'Indicação de outro site', 'nao-identificada' => 'Não identificada'];

    /** Buscadores reconhecidos pelo host de indicação. */
    private const SEARCH_ENGINES = ['google' => 'Google', 'bing' => 'Bing', 'duckduckgo' => 'DuckDuckGo', 'yahoo' => 'Yahoo', 'ecosia' => 'Ecosia'];

    public function capture(Request $request): void
    {
        $stored = (array) $request->session()->get(self::SESSION_KEY, []);
        $utm = $this->utm($request);

        if ($stored === []) {
            $request->session()->put(self::SESSION_KEY, [
                'entry_page' => Str::limit('/'.ltrim($request->path(), '/'), 250, ''),
                'referrer_host' => $this->referrerHost($request),
                ...$utm,
            ]);

            return;
        }

        if (filled($stored['utm_source'] ?? null) || filled($stored['referrer_host'] ?? null)) {
            return;
        }

        $referrer = $this->referrerHost($request);

        if ($utm !== [] || $referrer !== null) {
            $request->session()->put(self::SESSION_KEY, [...$stored, 'referrer_host' => $referrer, ...$utm]);
        }
    }

    /**
     * @return array<string, string|null>
     */
    public function current(Request $request): array
    {
        $stored = $request->hasSession() ? (array) $request->session()->get(self::SESSION_KEY, []) : [];

        return array_map(
            fn (string $field) => is_string($stored[$field] ?? null) ? $stored[$field] : null,
            array_combine(self::FIELDS, self::FIELDS),
        );
    }

    /**
     * Rótulo da origem para a equipe: campanha, busca orgânica, indicação de outro site ou não identificada.
     *
     * @param  array<string, mixed>  $origin
     */
    public static function label(array $origin): string
    {
        if (filled($origin['utm_source'] ?? null)) {
            return 'Campanha: '.implode(' / ', array_filter([$origin['utm_source'], $origin['utm_medium'] ?? null, $origin['utm_campaign'] ?? null]));
        }

        $host = (string) ($origin['referrer_host'] ?? '');

        if ($host === '') {
            return 'Não identificada';
        }

        foreach (self::SEARCH_ENGINES as $needle => $name) {
            if (preg_match('/(^|\.)'.$needle.'\.[a-z.]+$/', $host) === 1) {
                return 'Busca orgânica: '.$name;
            }
        }

        return 'Indicação: '.$host;
    }

    /**
     * Filtra leads por grupo de origem ou por campanha ("utm:fonte").
     *
     * @param  Builder<Lead>  $query
     */
    public static function applyFilter(Builder $query, string $origin): void
    {
        if (str_starts_with($origin, 'utm:')) {
            $query->where('utm_source', substr($origin, 4));

            return;
        }

        $fromSearch = function (Builder $query) {
            foreach (array_keys(self::SEARCH_ENGINES) as $engine) {
                $query->orWhere('referrer_host', 'like', $engine.'.%')->orWhere('referrer_host', 'like', '%.'.$engine.'.%');
            }
        };

        match ($origin) {
            'campanha' => $query->whereNotNull('utm_source'),
            'busca' => $query->whereNull('utm_source')->where($fromSearch),
            'indicacao' => $query->whereNull('utm_source')->whereNotNull('referrer_host')->whereNot($fromSearch),
            default => $query->whereNull('utm_source')->whereNull('referrer_host'),
        };
    }

    /**
     * UTM só vale com a fonte; valores com formato inesperado (ou que pareçam e-mail) são descartados.
     *
     * @return array<string, string>
     */
    private function utm(Request $request): array
    {
        $values = [];

        foreach (self::UTM_FIELDS as $field) {
            $value = $request->query($field);
            $value = is_string($value) ? Str::squish($value) : '';

            if ($value !== '' && preg_match('/^[\pL\pN][\pL\pN ._|+\-]{0,99}$/u', $value) === 1) {
                $values[$field] = $value;
            }
        }

        return isset($values['utm_source']) ? $values : [];
    }

    private function referrerHost(Request $request): ?string
    {
        $host = parse_url((string) $request->headers->get('referer'), PHP_URL_HOST);
        $host = is_string($host) ? Str::chopStart(Str::lower($host), 'www.') : '';

        if ($host === '' || strlen($host) > 150 || preg_match('/^[a-z0-9.\-]+$/', $host) !== 1) {
            return null;
        }

        return $host === Str::chopStart(Str::lower($request->getHost()), 'www.') ? null : $host;
    }
}
