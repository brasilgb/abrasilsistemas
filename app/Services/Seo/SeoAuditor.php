<?php

namespace App\Services\Seo;

use App\Models\BlogPost;
use App\Models\SeoAudit;
use App\Models\SeoAuditIssue;
use App\Models\SeoAuditPage;
use App\Models\SeoRedirect;
use App\Support\Seo;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Auditoria interna de SEO. Visita só URLs do próprio site (as páginas conhecidas, o
 * sitemap e os links internos encontrados nelas e nos artigos), com limite de páginas,
 * concorrência e timeout. Proteção contra SSRF: só os hosts permitidos (domínio canônico
 * e SEO_AUDIT_BASE_URL) são acessados, os redirecionamentos são seguidos manualmente
 * com a mesma verificação e um domínio canônico que resolva para IP privado é recusado,
 * salvo quando o próprio servidor o configurou em SEO_AUDIT_BASE_URL.
 */
class SeoAuditor
{
    /** @var list<array{url: string|null, subject: string|null, severity: string, code: string, message: string, context: array<string, mixed>|null}> */
    private array $issues = [];

    public function __construct(
        private SeoSettings $settings,
        private PageRegistry $registry,
        private SitemapBuilder $sitemap,
        private RedirectManager $redirects,
        private HtmlInspector $inspector,
        private ContentAnalyzer $analyzer,
    ) {}

    public function run(SeoAudit $audit): SeoAudit
    {
        $this->issues = [];
        $audit->forceFill(['status' => SeoAudit::STATUS_RUNNING, 'started_at' => now(), 'error' => null])->save();

        try {
            $this->assertSafeTarget();
            $result = $this->crawl($audit);
            $this->checkRedirects();
            $this->persist($audit, $result);
        } catch (Throwable $e) {
            Log::warning('seo.audit.failed', ['audit' => $audit->id, 'error' => $e->getMessage()]);
            $audit->forceFill(['status' => SeoAudit::STATUS_FAILED, 'finished_at' => now(), 'error' => Str::limit($e->getMessage(), 480)])->save();
        }

        return $audit->refresh();
    }

    /**
     * Hosts que a auditoria pode acessar (minúsculos).
     *
     * @return list<string>
     */
    public function allowedHosts(): array
    {
        $hosts = [$this->settings->canonicalHost()];
        $override = (string) config('seo.audit.base_url');

        if ($override !== '') {
            $hosts[] = strtolower((string) parse_url($override, PHP_URL_HOST));
        }

        return array_values(array_unique(array_filter($hosts)));
    }

    public function isAllowed(string $url): bool
    {
        $parts = parse_url($url);

        return is_array($parts)
            && in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
            && in_array(strtolower($parts['host'] ?? ''), $this->allowedHosts(), true)
            && ! isset($parts['user']);
    }

    /** O domínio canônico é editável no painel; não pode apontar para a rede interna. */
    private function assertSafeTarget(): void
    {
        if ((string) config('seo.audit.base_url') !== '') {
            return;
        }

        $host = $this->settings->canonicalHost();
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);

        if ($ips === []) {
            throw new RuntimeException("Não foi possível resolver o domínio {$host}.");
        }

        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new RuntimeException("O domínio {$host} aponta para um endereço interno ({$ip}). Configure SEO_AUDIT_BASE_URL no servidor para auditar um endereço interno.");
            }
        }
    }

    /** URL pública → URL efetivamente requisitada (SEO_AUDIT_BASE_URL, quando configurado). */
    private function requestUrl(string $url): string
    {
        $override = rtrim((string) config('seo.audit.base_url'), '/');

        if ($override === '') {
            return $url;
        }

        $parts = parse_url($url);

        return $override.($parts['path'] ?? '/').(isset($parts['query']) ? '?'.$parts['query'] : '');
    }

    private function normalize(string $url): string
    {
        $parts = parse_url($url);
        $path = $parts['path'] ?? '/';
        $path = $path === '/' ? '' : rtrim($path, '/');

        // URLs vindas do SEO_AUDIT_BASE_URL voltam para o domínio canônico.
        return $this->settings->baseUrl().$path.(isset($parts['query']) ? '?'.$parts['query'] : '');
    }

    /**
     * @return array{pages: array<string, array<string, mixed>>, inbound: array<string, int>}
     */
    private function crawl(SeoAudit $audit): array
    {
        $max = min((int) $this->settings->get('audit_max_pages') ?: 300, (int) config('seo.audit.max_pages_limit', 1000));
        $known = $this->registry->withEffective()->keyBy(fn (array $page) => $this->normalize($page['url']));
        $sitemapUrls = collect($this->sitemap->urls())->map(fn (array $url) => $this->normalize($url['loc']))->flip();

        $queue = $known->keys()->merge($sitemapUrls->keys())->unique()->values()->all();
        $sources = [];

        // Links internos dos artigos (o corpo vem do banco: sem SSR, ele não está no HTML inicial).
        BlogPost::query()->published()->get(['id', 'slug', 'title', 'body'])->each(function (BlogPost $post) use (&$queue, &$sources) {
            $from = $this->normalize($this->settings->url('/blog/'.$post->slug));

            foreach ($this->bodyLinks((string) $post->body) as $link) {
                $queue[] = $link;
                $sources[$link][] = $from;
            }
        });

        $queue = array_values(array_unique($queue));
        $audit->forceFill(['pages_total' => min(count($queue), $max)])->save();

        $pages = [];
        $inbound = [];
        $visited = [];

        while ($queue !== [] && count($visited) < $max) {
            $batch = [];

            while ($queue !== [] && count($batch) < (int) config('seo.audit.concurrency', 4) && count($visited) + count($batch) < $max) {
                $url = array_shift($queue);

                if (! isset($visited[$url]) && $this->isAllowed($url)) {
                    $batch[] = $url;
                    $visited[$url] = true;
                }
            }

            foreach ($this->fetchBatch($batch) as $url => $fetched) {
                $entry = $known->get($url);
                $page = $this->evaluate($url, $fetched, $entry, $sitemapUrls->has($url), $sources[$url] ?? []);
                $pages[$url] = $page;

                foreach ($page['links'] as $link) {
                    if ($link !== $url) {
                        $inbound[$link] = ($inbound[$link] ?? 0) + 1;
                        $sources[$link][] = $url;
                    }

                    if (! isset($visited[$link])) {
                        $queue[] = $link;
                    }
                }
            }

            $audit->forceFill(['pages_scanned' => count($pages)])->save();
        }

        if ($queue !== [] && count($visited) >= $max) {
            $this->issue(null, null, SeoAuditIssue::OPPORTUNITY, 'audit_limit', "A auditoria parou no limite de {$max} páginas. Aumente o limite em SEO → Configurações se o site tiver mais URLs.");
        }

        $this->checkDuplicates($pages);
        $this->checkSite($pages, $inbound, $known);

        return ['pages' => $pages, 'inbound' => $inbound];
    }

    /**
     * @param  list<string>  $urls
     * @return array<string, array{status: int|null, finalUrl: string|null, chain: list<string>, headers: array<string, string>, body: string, ms: int|null, error: string|null}>
     */
    private function fetchBatch(array $urls): array
    {
        $results = [];
        $pending = array_combine($urls, array_map(fn ($url) => ['current' => $url, 'chain' => [], 'started' => microtime(true)], $urls));

        for ($hop = 0; $pending !== [] && $hop <= (int) config('seo.audit.max_redirects', 5); $hop++) {
            $responses = Http::pool(fn (Pool $pool) => array_map(
                fn (string $url) => $pool->as($url)
                    ->withHeaders(array_filter([
                        'User-Agent' => 'ABrasilSEOAudit/1.0 (+'.$this->settings->url('/').')',
                        'Accept' => 'text/html',
                        'Host' => config('seo.audit.base_url') ? $this->settings->canonicalHost() : null,
                    ]))
                    ->timeout((int) config('seo.audit.timeout', 10))
                    ->withoutRedirecting()
                    ->get($this->requestUrl($pending[$url]['current'])),
                array_keys($pending),
            ));

            foreach (array_keys($pending) as $url) {
                $state = $pending[$url];
                $response = $responses[$url] ?? null;

                if (! $response instanceof Response) {
                    $results[$url] = ['status' => null, 'finalUrl' => null, 'chain' => $state['chain'], 'headers' => [], 'body' => '', 'ms' => null, 'error' => $response instanceof Throwable ? Str::limit($response->getMessage(), 200) : 'Sem resposta'];
                    unset($pending[$url]);

                    continue;
                }

                $location = $response->header('Location');

                if ($response->redirect() && $location !== '') {
                    $next = HtmlInspector::resolve($location, $state['current']);
                    $pending[$url]['chain'][] = $response->status().' → '.$next;

                    // Redirecionamento para fora dos hosts permitidos: registra e não segue.
                    if (! $this->isAllowed($next)) {
                        $results[$url] = ['status' => $response->status(), 'finalUrl' => $next, 'chain' => $pending[$url]['chain'], 'headers' => [], 'body' => '', 'ms' => null, 'error' => null];
                        unset($pending[$url]);

                        continue;
                    }

                    $pending[$url]['current'] = $this->normalize($next);

                    continue;
                }

                $body = (string) $response->body();

                $results[$url] = [
                    'status' => $response->status(),
                    'finalUrl' => $state['current'],
                    'chain' => $state['chain'],
                    'headers' => ['x-robots-tag' => (string) $response->header('X-Robots-Tag'), 'content-type' => (string) $response->header('Content-Type')],
                    'body' => strlen($body) > (int) config('seo.audit.max_bytes') ? '' : $body,
                    'ms' => (int) round((microtime(true) - $state['started']) * 1000),
                    'error' => strlen($body) > (int) config('seo.audit.max_bytes') ? 'Página maior que o limite da auditoria' : null,
                ];
                unset($pending[$url]);
            }
        }

        foreach ($pending as $url => $state) {
            $results[$url] = ['status' => null, 'finalUrl' => $state['current'], 'chain' => $state['chain'], 'headers' => [], 'body' => '', 'ms' => null, 'error' => 'Excesso de redirecionamentos'];
        }

        return $results;
    }

    /**
     * @param  array<string, mixed>  $fetched
     * @param  array<string, mixed>|null  $entry
     * @param  list<string>  $linkedFrom
     * @return array<string, mixed>
     */
    private function evaluate(string $url, array $fetched, ?array $entry, bool $inSitemap, array $linkedFrom): array
    {
        $subject = $entry['key'] ?? null;
        $context = array_filter(['editUrl' => $entry['editUrl'] ?? null, 'contentUrl' => $entry['contentUrl'] ?? null, 'label' => $entry['label'] ?? null, 'linkedFrom' => array_slice(array_values(array_unique($linkedFrom)), 0, 5) ?: null]);
        $status = $fetched['status'];
        $page = [
            'url' => $url, 'subject' => $subject, 'status_code' => $status, 'final_url' => $fetched['finalUrl'],
            'title' => null, 'description' => null, 'canonical' => null, 'robots' => null, 'indexable' => false,
            'in_sitemap' => $inSitemap, 'h1_count' => null, 'images_without_alt' => 0, 'internal_links' => 0,
            'response_ms' => $fetched['ms'], 'html_bytes' => strlen($fetched['body']), 'links' => [],
        ];

        if ($status === null) {
            $this->issue($url, $subject, SeoAuditIssue::CRITICAL, 'fetch_failed', 'Não foi possível acessar a página: '.($fetched['error'] ?? 'erro desconhecido').'.', $context);

            return $page;
        }

        if ($fetched['chain'] !== []) {
            $severity = $inSitemap || $entry ? SeoAuditIssue::WARNING : SeoAuditIssue::OPPORTUNITY;
            $this->issue($url, $subject, $severity, count($fetched['chain']) > 1 ? 'redirect_chain' : 'redirected_link', (count($fetched['chain']) > 1 ? 'Cadeia de redirecionamentos: ' : 'Link interno redirecionado: ').implode(', ', $fetched['chain']).'. Aponte direto para o destino final.', $context);
        }

        if ($status >= 400) {
            $code = $linkedFrom !== [] && ! $entry ? 'broken_link' : 'http_error';
            $this->issue($url, $subject, SeoAuditIssue::CRITICAL, $code, ($code === 'broken_link' ? 'Link interno quebrado' : 'Página com erro')." (HTTP {$status}).", $context);

            return $page;
        }

        if (! str_contains($fetched['headers']['content-type'] ?? 'text/html', 'html') || $fetched['body'] === '') {
            return $page;
        }

        $html = $this->inspector->inspect($fetched['body'], (string) $fetched['finalUrl']);
        $robots = trim(($html['robots'] ?? '').','.($fetched['headers']['x-robots-tag'] ?? ''), ',');
        $canonical = $html['canonical'] ? $this->normalize($html['canonical']) : null;
        $indexable = Seo::isIndexable($robots) && ($canonical === null || $canonical === $url);
        $internal = array_values(array_filter(array_map(fn ($link) => $this->isAllowed($link) ? $this->normalize($link) : null, $html['links'])));

        $page = [...$page,
            'title' => $html['title'], 'description' => $html['description'], 'canonical' => $html['canonical'], 'robots' => $robots ?: null,
            'indexable' => $indexable, 'h1_count' => $html['hasRenderedContent'] ? $html['h1Count'] : null,
            'images_without_alt' => $html['imagesWithoutAlt'], 'internal_links' => count($internal), 'links' => array_values(array_unique($internal)),
        ];

        // Links quebrados são checados, mas só as páginas conhecidas recebem a análise completa.
        if (! $entry && ! $inSitemap) {
            return $page;
        }

        $titleLength = mb_strlen((string) $html['title']);

        if ($titleLength === 0) {
            $this->issue($url, $subject, SeoAuditIssue::CRITICAL, 'missing_title', 'Página sem <title>.', $context);
        } elseif ($titleLength > 65) {
            $this->issue($url, $subject, SeoAuditIssue::OPPORTUNITY, 'long_title', "Título com {$titleLength} caracteres; deve ser cortado no resultado de busca.", $context);
        }

        if (blank($html['description'])) {
            $this->issue($url, $subject, $indexable ? SeoAuditIssue::WARNING : SeoAuditIssue::OPPORTUNITY, 'missing_description', 'Página sem meta description.', $context);
        } elseif ($html['descriptionCount'] > 1) {
            $this->issue($url, $subject, SeoAuditIssue::WARNING, 'duplicate_description_tag', 'Mais de uma meta description no HTML.', $context);
        }

        if ($html['canonicalCount'] === 0) {
            $this->issue($url, $subject, SeoAuditIssue::WARNING, 'missing_canonical', 'Página sem link canonical.', $context);
        } elseif ($html['canonicalCount'] > 1) {
            $this->issue($url, $subject, SeoAuditIssue::CRITICAL, 'multiple_canonicals', 'Mais de um canonical no HTML: o buscador pode ignorar todos.', $context);
        } elseif ($canonical !== $url) {
            $this->issue($url, $subject, $inSitemap ? SeoAuditIssue::CRITICAL : SeoAuditIssue::WARNING, 'canonical_mismatch', 'O canonical aponta para '.$html['canonical'].($inSitemap ? ', mas a página está no sitemap.' : '. Confirme se é intencional.'), $context);
        }

        if (! Seo::isIndexable($robots) && $inSitemap) {
            $this->issue($url, $subject, SeoAuditIssue::CRITICAL, 'noindex_in_sitemap', 'Página com noindex listada no sitemap.', $context);
        }

        if ($indexable && ! $inSitemap) {
            $this->issue($url, $subject, SeoAuditIssue::WARNING, 'missing_from_sitemap', 'Página indexável fora do sitemap.', $context);
        }

        if ($html['hasRenderedContent'] && $html['h1Count'] === 0) {
            $this->issue($url, $subject, SeoAuditIssue::WARNING, 'missing_h1', 'Página sem H1 no HTML.', $context);
        } elseif ($html['hasRenderedContent'] && $html['h1Count'] > 1) {
            $this->issue($url, $subject, SeoAuditIssue::OPPORTUNITY, 'multiple_h1', "Página com {$html['h1Count']} H1.", $context);
        }

        if ($html['imagesWithoutAlt'] > 0) {
            $this->issue($url, $subject, SeoAuditIssue::WARNING, 'image_without_alt', "{$html['imagesWithoutAlt']} imagem(ns) sem atributo alt.", $context);
        }

        if (blank($html['ogImage']) && $indexable) {
            $this->issue($url, $subject, SeoAuditIssue::OPPORTUNITY, 'missing_og_image', 'Sem og:image: o link compartilhado sai sem imagem.', $context);
        }

        if ($html['jsonLdErrors'] > 0) {
            $this->issue($url, $subject, SeoAuditIssue::CRITICAL, 'invalid_json_ld', 'JSON-LD com sintaxe inválida.', $context);
        }

        foreach ($html['jsonLd'] as $block) {
            foreach (StructuredData::validate($block) as $problem) {
                if ($problem['severity'] !== SeoAuditIssue::OPPORTUNITY) {
                    $this->issue($url, $subject, $problem['severity'], 'structured_data', $problem['message'], $context);
                }
            }
        }

        if (($fetched['ms'] ?? 0) > 2000) {
            $this->issue($url, $subject, SeoAuditIssue::OPPORTUNITY, 'slow_response', "Resposta em {$fetched['ms']} ms (acima de 2 s).", $context);
        }

        // Artigos: o corpo vem do banco (é renderizado no navegador).
        if (($entry['type'] ?? null) === 'post') {
            $this->checkPostBody((int) $entry['id'], $url, $subject, $context);
        }

        return $page;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function checkPostBody(int $postId, string $url, ?string $subject, array $context): void
    {
        $post = BlogPost::query()->find($postId);

        if (! $post) {
            return;
        }

        $analysis = $this->analyzer->analyze(['title' => $post->title, 'excerpt' => $post->excerpt, 'slug' => $post->slug, 'body' => (string) $post->body, 'post_id' => $post->id]);

        foreach ($analysis['checks'] as $check) {
            if (in_array($check['id'], ['headings', 'images', 'duplicate'], true) && $check['status'] === ContentAnalyzer::PROBLEM) {
                $this->issue($url, $subject, $check['id'] === 'images' ? SeoAuditIssue::WARNING : SeoAuditIssue::WARNING, 'content_'.$check['id'], $check['label'].': '.$check['message'], $context);
            }
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $pages
     */
    private function checkDuplicates(array $pages): void
    {
        foreach (['title' => 'Título', 'description' => 'Meta description'] as $field => $label) {
            collect($pages)->filter(fn ($page) => $page['indexable'] && filled($page[$field]))
                ->groupBy(fn ($page) => Str::lower(trim((string) $page[$field])))
                ->filter(fn (Collection $group) => $group->count() > 1)
                ->each(function (Collection $group) use ($field, $label) {
                    foreach ($group as $page) {
                        $this->issue($page['url'], $page['subject'], $field === 'title' ? SeoAuditIssue::WARNING : SeoAuditIssue::OPPORTUNITY, 'duplicate_'.$field, "{$label} repetido em {$group->count()} páginas: ".$group->pluck('url')->reject(fn ($url) => $url === $page['url'])->implode(', '), ['editUrl' => $this->registry->findByPath((string) parse_url($page['url'], PHP_URL_PATH))['editUrl'] ?? null]);
                    }
                });
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $pages
     * @param  array<string, int>  $inbound
     * @param  Collection<string, array<string, mixed>>  $known
     */
    private function checkSite(array $pages, array $inbound, Collection $known): void
    {
        $withHtml = collect($pages)->filter(fn ($page) => $page['html_bytes'] > 0);

        if ($withHtml->isNotEmpty() && $withHtml->every(fn ($page) => $page['h1_count'] === null)) {
            $this->issue(null, null, SeoAuditIssue::OPPORTUNITY, 'client_side_rendering', 'O conteúdo das páginas é montado no navegador (Inertia sem SSR): o HTML inicial traz título, metatags, canonical e JSON-LD, mas não o texto, os H1 e os links. O Google renderiza JavaScript, porém com atraso; outros buscadores e redes sociais leem só o HTML inicial. Recomendação: ativar o SSR do Inertia.');
        }

        // Artigos sem nenhum link de outros artigos (a listagem do blog sempre linka; aqui o foco são links contextuais).
        $articleLinks = BlogPost::query()->published()->get(['slug', 'body'])
            ->flatMap(fn (BlogPost $post) => array_diff($this->bodyLinks((string) $post->body), [$this->normalize($this->settings->url('/blog/'.$post->slug))]))
            ->countBy();

        foreach ($known->where('type', 'post') as $url => $entry) {
            if (($articleLinks[$url] ?? 0) === 0 && $entry['indexable']) {
                $this->issue($url, $entry['key'], SeoAuditIssue::OPPORTUNITY, 'orphan_article', 'Nenhum outro artigo linka para este: ele só é alcançado pela listagem do blog. Crie links a partir de artigos e páginas do mesmo tema.', ['editUrl' => $entry['editUrl'], 'label' => $entry['label']]);
            }
        }
    }

    private function checkRedirects(): void
    {
        SeoRedirect::query()->where('is_active', true)->where('status_code', '!=', 410)->get()->each(function (SeoRedirect $redirect) {
            $chain = $this->redirects->chain($redirect->source_path, $redirect->target_url, $redirect->id);
            $context = ['editUrl' => '/admin/seo/redirecionamentos/'.$redirect->id.'/edit'];

            if ($chain['loop']) {
                $this->issue($this->settings->url($redirect->source_path), null, SeoAuditIssue::CRITICAL, 'redirect_loop', 'Loop de redirecionamento: '.implode(' → ', [$redirect->source_path, $redirect->target_url, ...$chain['hops']]), $context);
            } elseif ($chain['hops'] !== []) {
                $this->issue($this->settings->url($redirect->source_path), null, SeoAuditIssue::WARNING, 'redirect_rule_chain', 'Regra em cadeia: '.implode(' → ', [$redirect->source_path, $redirect->target_url, ...$chain['hops']]).'. Aponte direto para o destino final.', $context);
            }

            $target = (string) $redirect->target_url;
            $finalPath = end($chain['hops']) ?: $target;

            if (str_starts_with((string) $finalPath, '/') && ($gone = $this->redirects->resolve((string) $finalPath)) && $gone[1] === 410) {
                $this->issue($this->settings->url($redirect->source_path), null, SeoAuditIssue::CRITICAL, 'redirect_to_gone', "Redireciona para {$finalPath}, que está marcada como removida (410).", $context);
            }
        });
    }

    /** @return list<string> */
    private function bodyLinks(string $body): array
    {
        if (! preg_match_all('/<a\s[^>]*href=["\']([^"\']+)["\']/i', $body, $matches)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(function (string $href) {
            if (str_starts_with($href, '#') || preg_match('#^(mailto|tel|javascript):#i', $href)) {
                return null;
            }

            $absolute = HtmlInspector::resolve(html_entity_decode($href), $this->settings->url('/blog/'));

            return $this->isAllowed($absolute) ? $this->normalize($absolute) : null;
        }, $matches[1]))));
    }

    /**
     * @param  array<string, mixed>|null  $context
     */
    private function issue(?string $url, ?string $subject, string $severity, string $code, string $message, ?array $context = null): void
    {
        $this->issues[] = ['url' => $url, 'subject' => $subject, 'severity' => $severity, 'code' => $code, 'message' => Str::limit($message, 495), 'context' => $context ?: null];
    }

    /**
     * @param  array{pages: array<string, array<string, mixed>>, inbound: array<string, int>}  $result
     */
    private function persist(SeoAudit $audit, array $result): void
    {
        $now = now();

        foreach (array_chunk($result['pages'], 100, true) as $chunk) {
            SeoAuditPage::query()->insert(array_map(fn (array $page) => [
                ...collect($page)->except('links')->all(),
                'seo_audit_id' => $audit->id,
                'title' => Str::limit((string) $page['title'], 495, '') ?: null,
                'description' => Str::limit((string) $page['description'], 995, '') ?: null,
                'inbound_links' => $result['inbound'][$page['url']] ?? 0,
                'created_at' => $now,
                'updated_at' => $now,
            ], array_values($chunk)));
        }

        foreach (array_chunk($this->issues, 200) as $chunk) {
            SeoAuditIssue::query()->insert(array_map(fn (array $issue) => [
                ...$issue,
                'seo_audit_id' => $audit->id,
                'context' => $issue['context'] ? json_encode($issue['context'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                'created_at' => $now,
                'updated_at' => $now,
            ], $chunk));
        }

        $issues = collect($this->issues);
        $known = collect($result['pages'])->filter(fn ($page) => $page['subject'] !== null || $page['in_sitemap']);

        $audit->forceFill([
            'status' => SeoAudit::STATUS_COMPLETED,
            'finished_at' => now(),
            'pages_scanned' => count($result['pages']),
            'critical_count' => $issues->where('severity', SeoAuditIssue::CRITICAL)->count(),
            'warning_count' => $issues->where('severity', SeoAuditIssue::WARNING)->count(),
            'opportunity_count' => $issues->where('severity', SeoAuditIssue::OPPORTUNITY)->count(),
            'summary' => [
                'pages' => $known->count(),
                'indexable' => $known->where('indexable', true)->count(),
                'nonIndexable' => $known->where('indexable', false)->count(),
                'missingMeta' => $issues->whereIn('code', ['missing_title', 'missing_description'])->count(),
                'duplicateMeta' => $issues->whereIn('code', ['duplicate_title', 'duplicate_description', 'duplicate_description_tag'])->count(),
                'imagesWithoutAlt' => collect($result['pages'])->sum('images_without_alt') + $issues->where('code', 'content_images')->count(),
                'canonicalIssues' => $issues->whereIn('code', ['missing_canonical', 'multiple_canonicals', 'canonical_mismatch'])->count(),
                'brokenLinks' => $issues->where('code', 'broken_link')->count(),
                'redirectIssues' => $issues->whereIn('code', ['redirect_chain', 'redirect_loop', 'redirect_rule_chain', 'redirect_to_gone', 'redirected_link'])->count(),
                'codes' => $issues->countBy('code')->all(),
            ],
        ])->save();
    }
}
