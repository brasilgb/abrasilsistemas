<?php

namespace App\Services\Seo;

use App\Models\SeoRedirect;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Regras dos redirecionamentos: normalização de origem/destino, detecção de loops e
 * cadeias, mapa em cache usado pelo middleware ApplySeoRedirects e os redirecionamentos
 * automáticos (troca de slug e artigo removido).
 */
class RedirectManager
{
    private const CACHE_KEY = 'seo.redirects.map.v1';

    private const MAX_HOPS = 10;

    /** Origens que nunca podem ser redirecionadas (painel, autenticação, API, assets). */
    public const PROTECTED_PREFIXES = [
        '/admin', '/atendimento', '/solicitacoes', '/users', '/settings', '/login', '/logout', '/register',
        '/forgot-password', '/reset-password', '/two-factor-challenge', '/user', '/email', '/api', '/webhooks',
        '/build', '/storage', '/up', '/sitemap.xml', '/sitemaps', '/robots.txt',
    ];

    public function __construct(private SeoSettings $settings) {}

    public static function normalizeSource(string $value): string
    {
        $path = (string) (parse_url(trim($value), PHP_URL_PATH) ?? '');
        $path = '/'.trim(rawurldecode($path), '/');

        return $path;
    }

    /** Destino interno vira caminho; externo precisa ser http(s). Retorna null se inválido. */
    public function normalizeTarget(string $value): ?string
    {
        $value = trim($value);

        if (str_starts_with($value, '/') && ! str_starts_with($value, '//')) {
            return $value === '/' ? '/' : rtrim($value, '/');
        }

        $parts = parse_url($value);

        if (! is_array($parts) || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true) || empty($parts['host'])) {
            return null;
        }

        $host = strtolower($parts['host']);

        if ($host === $this->settings->canonicalHost() || $host === 'www.'.$this->settings->canonicalHost()) {
            $path = ($parts['path'] ?? '/') ?: '/';

            return ($path === '/' ? '/' : rtrim($path, '/')).(isset($parts['query']) ? '?'.$parts['query'] : '');
        }

        return $value;
    }

    public static function isProtected(string $path): bool
    {
        foreach (self::PROTECTED_PREFIXES as $prefix) {
            if ($path === $prefix || str_starts_with($path, rtrim($prefix, '/').'/')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Mapa ativo: origem => [destino, código]. Fica em cache até qualquer alteração.
     *
     * @return array<string, array{0: string|null, 1: int, 2: int}>
     */
    public function map(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, fn () => SeoRedirect::query()
            ->where('is_active', true)
            ->get(['id', 'source_path', 'target_url', 'status_code'])
            ->mapWithKeys(fn (SeoRedirect $redirect) => [$redirect->source_path => [$redirect->target_url, $redirect->status_code, $redirect->id]])
            ->all());
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array{0: string|null, 1: int, 2: int}|null
     */
    public function resolve(string $path): ?array
    {
        return $this->map()[self::normalizeSource($path)] ?? null;
    }

    /**
     * Segue o destino pelo mapa e devolve os saltos. loop=true quando volta à origem
     * ou repete um endereço.
     *
     * @return array{hops: list<string>, loop: bool}
     */
    public function chain(string $source, ?string $target, ?int $ignoreId = null): array
    {
        $map = collect($this->map())->reject(fn (array $rule) => $ignoreId !== null && $rule[2] === $ignoreId)->all();
        $seen = [$source => true];
        $hops = [];
        $current = $target;

        while ($current !== null && str_starts_with($current, '/') && count($hops) < self::MAX_HOPS) {
            $path = self::normalizeSource($current);

            if (isset($seen[$path])) {
                return ['hops' => $hops, 'loop' => true];
            }

            $seen[$path] = true;

            if (! isset($map[$path]) || $map[$path][1] === 410) {
                break;
            }

            $current = $map[$path][0];
            $hops[] = (string) $current;
        }

        return ['hops' => $hops, 'loop' => count($hops) >= self::MAX_HOPS];
    }

    /**
     * Validação de uma regra. errors impedem salvar; warnings são mostrados ao usuário.
     *
     * @return array{errors: array<string, string>, warnings: list<string>, source: string, target: string|null}
     */
    public function validate(string $source, ?string $target, int $code, ?int $ignoreId = null): array
    {
        $errors = [];
        $warnings = [];
        $source = self::normalizeSource($source);
        $normalizedTarget = $code === 410 ? null : $this->normalizeTarget((string) $target);

        if ($source === '/') {
            $errors['source_path'] = 'A página inicial não pode ser redirecionada.';
        } elseif (self::isProtected($source)) {
            $errors['source_path'] = 'Esse caminho é do painel, da autenticação ou de arquivos do sistema e não pode ser redirecionado.';
        }

        if ($code !== 410 && $normalizedTarget === null) {
            $errors['target_url'] = 'Informe um caminho interno (/pagina) ou uma URL http(s) completa.';
        }

        if ($normalizedTarget !== null && self::normalizeSource($normalizedTarget) === $source && str_starts_with($normalizedTarget, '/')) {
            $errors['target_url'] = 'O destino é igual à origem.';
        }

        if ($errors === [] && $normalizedTarget !== null) {
            $chain = $this->chain($source, $normalizedTarget, $ignoreId);

            if ($chain['loop']) {
                $errors['target_url'] = 'Esse destino cria um loop de redirecionamento ('.implode(' → ', [$source, $normalizedTarget, ...$chain['hops']]).').';
            } elseif ($chain['hops'] !== []) {
                $warnings[] = 'O destino também é redirecionado (cadeia: '.implode(' → ', [$source, $normalizedTarget, ...$chain['hops']]).'). Prefira apontar direto para o destino final.';
            }

            // Regras que apontam para esta origem passam a formar cadeia.
            $inbound = collect($this->map())->filter(fn (array $rule, string $from) => $rule[0] === $source && $rule[2] !== $ignoreId)->keys();

            if ($inbound->isNotEmpty()) {
                $warnings[] = 'Outros redirecionamentos apontam para esta origem ('.$inbound->implode(', ').'): eles vão formar cadeia.';
            }
        }

        return ['errors' => $errors, 'warnings' => $warnings, 'source' => $source, 'target' => $normalizedTarget];
    }

    /**
     * Slug alterado em uma página publicada: 301 da URL antiga para a nova. Regras que
     * apontavam para a antiga passam a apontar direto para a nova (sem cadeia), e uma
     * regra que tivesse a nova URL como origem é desativada (a página voltou a existir).
     */
    public function redirectMovedPath(string $from, string $to, ?int $userId): SeoRedirect
    {
        $from = self::normalizeSource($from);
        $to = self::normalizeSource($to);

        SeoRedirect::query()->where('target_url', $from)->update(['target_url' => $to, 'updated_by' => $userId]);
        SeoRedirect::query()->where('source_path', $to)->update(['is_active' => false, 'updated_by' => $userId]);

        $redirect = SeoRedirect::query()->updateOrCreate(['source_path' => $from], [
            'target_url' => $to,
            'status_code' => 301,
            'is_active' => true,
            'notes' => 'Criado automaticamente: o endereço da página mudou.',
            'updated_by' => $userId,
        ]);

        if ($redirect->wasRecentlyCreated) {
            $redirect->forceFill(['created_by' => $userId])->save();
        }

        $this->flush();

        return $redirect;
    }

    /** Página removida de propósito: responde 410 (Gone), a menos que já exista uma regra. */
    public function markGone(string $path, ?int $userId, string $notes): ?SeoRedirect
    {
        $path = self::normalizeSource($path);

        if (SeoRedirect::query()->where('source_path', $path)->exists()) {
            return null;
        }

        $redirect = SeoRedirect::query()->create([
            'source_path' => $path,
            'target_url' => null,
            'status_code' => 410,
            'is_active' => true,
            'notes' => $notes,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);
        $this->flush();

        return $redirect;
    }

    public function recordHit(int $id): void
    {
        SeoRedirect::query()->whereKey($id)->update(['hits' => DB::raw('hits + 1'), 'last_hit_at' => now()]);
    }
}
