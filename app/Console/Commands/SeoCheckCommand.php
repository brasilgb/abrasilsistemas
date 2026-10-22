<?php

namespace App\Console\Commands;

use App\Services\Seo\HtmlInspector;
use App\Services\Seo\SeoSettings;
use App\Services\Seo\SitemapBuilder;
use App\Services\Seo\StructuredData;
use App\Support\PublicPages;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verificação de prontidão para indexação (ABRASIL-SEO-FINAL-01 §6), para rodar no servidor
 * depois do deploy. Detecta o erro mais caro: produção publicada com noindex ou robots
 * fechado por APP_URL/domínio canônico errado ou SEO_BLOCK_INDEXING ligado.
 * Sem --live, simula as requisições dentro da aplicação; com --live, acessa o site publicado.
 */
class SeoCheckCommand extends Command
{
    protected $signature = 'seo:check {--live : Acessa o site publicado no domínio canônico (HTTP real)}';

    protected $description = 'Confere se o site está pronto para ser indexado (canonical, robots, sitemap e JSON-LD)';

    private int $failures = 0;

    public function handle(SeoSettings $settings, SitemapBuilder $sitemap, HtmlInspector $inspector): int
    {
        $base = $settings->baseUrl();
        $host = $settings->canonicalHost();

        $this->line("Domínio canônico: {$base}");

        $this->check(str_starts_with($base, 'https://'), 'Domínio canônico usa HTTPS', 'O domínio canônico não usa HTTPS: confira APP_URL (ABRASILSISTEMA_APP_URL no compose) ou Painel → SEO → Configurações.');
        $this->check($host !== '' && ! preg_match('/(^localhost$|\.localhost$|\.local$|\.test$|^\d+\.\d+\.\d+\.\d+$)/', $host), 'Domínio canônico é público', "O domínio canônico \"{$host}\" não é público: todas as páginas sairiam com noindex.");
        $this->check(! config('seo.block_indexing'), 'SEO_BLOCK_INDEXING desligado', 'SEO_BLOCK_INDEXING=true: correto em homologação, nunca em produção.');
        $this->check(! $settings->get('block_indexing'), 'Bloqueio de indexação do painel desligado', 'Painel → SEO → Configurações está bloqueando a indexação.');

        $urls = $sitemap->urls();
        $foreign = array_filter($urls, fn (array $url) => ! str_starts_with($url['loc'], $base));
        $this->check($urls !== [] && $foreign === [], 'Sitemap com '.count($urls).' URLs, todas no domínio canônico', 'O sitemap está vazio ou tem URLs fora do domínio canônico.');

        $invalid = collect(['home', 'servicos', 'servico:'.PublicPages::SEO_SERVICE, 'sistemas', 'contato'])
            ->filter(fn (string $id) => collect(StructuredData::validate(app(StructuredData::class)->forPage($id)))->whereIn('severity', ['critical', 'warning'])->isNotEmpty());
        $this->check($invalid->isEmpty(), 'JSON-LD válido nas páginas principais', 'JSON-LD com problemas em: '.$invalid->implode(', '));

        foreach (['/robots.txt', '/', '/'.PublicPages::SEO_SERVICE, '/blog'] as $path) {
            [$status, $body, $robotsHeader] = $this->option('live') ? $this->fetch($base.$path) : $this->simulate($base.$path);

            if ($path === '/robots.txt') {
                $this->check($status === 200 && ! preg_match('#^Disallow:\s*/\s*$#m', $body) && str_contains($body, 'Sitemap: '.$base.'/sitemap.xml'), 'robots.txt aberto e apontando para o sitemap', 'robots.txt bloqueia o site inteiro ou não aponta para o sitemap.');

                continue;
            }

            $page = $status === 200 ? $inspector->inspect($body, $base.$path) : null;
            $robots = strtolower(($page['robots'] ?? '').' '.$robotsHeader);
            $canonical = $page['canonical'] ?? null;

            $this->check($status === 200, "{$path} responde 200", "{$path} respondeu {$status}.");
            $this->check($page !== null && ! str_contains($robots, 'noindex'), "{$path} indexável", "{$path} está com noindex.");
            $this->check($canonical !== null && rtrim($canonical, '/') === rtrim($base.$path, '/'), "{$path} com canonical correto", "{$path}: canonical \"{$canonical}\" diferente de \"{$base}{$path}\".");
        }

        $this->newLine();

        if ($this->failures > 0) {
            $this->error("{$this->failures} problema(s) impedem a indexação correta.");

            return self::FAILURE;
        }

        $this->info('Pronto para indexação.');

        return self::SUCCESS;
    }

    private function check(bool $ok, string $label, string $problem): void
    {
        if ($ok) {
            $this->line("  <fg=green>✓</> {$label}");

            return;
        }

        $this->failures++;
        $this->line("  <fg=red>✗</> {$problem}");
    }

    /** @return array{int, string, string} */
    private function simulate(string $url): array
    {
        $response = app(Kernel::class)->handle(Request::create($url, 'GET'));

        return [$response->getStatusCode(), (string) $response->getContent(), $this->robotsHeader($response)];
    }

    /** @return array{int, string, string} */
    private function fetch(string $url): array
    {
        $response = Http::timeout(15)->withoutRedirecting()->get($url);

        return [$response->status(), $response->body(), (string) $response->header('X-Robots-Tag')];
    }

    private function robotsHeader(Response $response): string
    {
        return (string) $response->headers->get('X-Robots-Tag');
    }
}
