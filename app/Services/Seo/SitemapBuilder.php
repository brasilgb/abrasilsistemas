<?php

namespace App\Services\Seo;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Support\PublicPages;
use App\Support\Seo;
use Illuminate\Support\Collection;

/**
 * URLs do sitemap: só páginas canônicas e indexáveis. Uma página com noindex, com
 * canonical apontando para outra URL ou não publicada fica de fora (com o motivo,
 * exibido em Painel → SEO → Sitemap e indexação).
 */
class SitemapBuilder
{
    public function __construct(private PageRegistry $registry, private SeoSettings $settings) {}

    /**
     * Máximo de URLs por arquivo; acima disso o /sitemap.xml vira um índice de sitemaps.
     *
     * @return int<1, max>
     */
    public function limit(): int
    {
        return max(1, (int) config('seo.sitemap_limit', 45000));
    }

    /**
     * @return list<array{loc: string, lastmod?: string|null, changefreq: string, priority: string}>
     */
    public function pageUrls(): array
    {
        return $this->entries()->where('included', true)->where('source', 'page')
            ->map(fn (array $entry) => $entry['sitemap'])->values()->all();
    }

    /**
     * @return list<array{loc: string, lastmod?: string|null, changefreq: string, priority: string}>
     */
    public function urls(): array
    {
        if ($this->settings->get('block_indexing') || config('seo.block_indexing')) {
            return [];
        }

        return $this->entries()->where('included', true)->map(fn (array $entry) => $entry['sitemap'])->values()->all();
    }

    /**
     * Todas as URLs candidatas, incluídas ou não, com o motivo da exclusão.
     *
     * @return Collection<int, array{source: string, label: string, url: string, included: bool, reason: string|null, editUrl: string|null, sitemap: array<string, string|null>}>
     */
    public function entries(): Collection
    {
        $entries = collect();
        $pages = $this->registry->withEffective($this->registry->all(withPosts: false));
        $catalog = PublicPages::PAGES;

        foreach ($pages as $page) {
            $static = $catalog[$page['id']] ?? null;
            $entries->push($this->entry('page', $page, [
                'loc' => $page['url'],
                'changefreq' => $static['changefreq'] ?? 'monthly',
                'priority' => $static['priority'] ?? (str_starts_with($page['id'], 'servico:') ? '0.9' : '0.8'),
            ]));
        }

        $posts = BlogPost::query()->latest('published_at')->get();
        $postPages = $this->registry->withEffective($posts->map(fn (BlogPost $post) => $this->registry->postEntry($post)));

        foreach ($postPages as $index => $page) {
            $post = $posts[$index];
            $published = $post->status === 'published' && $post->published_at?->isPast();
            $entry = $this->entry('post', $page, [
                'loc' => $page['url'],
                'lastmod' => $post->updated_at?->toAtomString(),
                'changefreq' => 'monthly',
                'priority' => '0.6',
            ]);

            if (! $published) {
                $entry['included'] = false;
                $entry['reason'] = $post->status === 'published' ? 'Publicação agendada' : 'Rascunho';
            } elseif (! $this->settings->get('sitemap_include_blog')) {
                $entry['included'] = false;
                $entry['reason'] = 'Artigos desativados nas preferências de sitemap';
            }

            $entries->push($entry);
        }

        if ($this->settings->get('sitemap_include_categories')) {
            BlogCategory::query()
                ->whereHas('posts', fn ($query) => $query->where('status', 'published')->where('published_at', '<=', now()))
                ->orderBy('name')
                ->get()
                ->each(fn (BlogCategory $category) => $entries->push([
                    'source' => 'category',
                    'label' => 'Categoria: '.$category->name,
                    'url' => $this->settings->url('/blog?category='.$category->slug),
                    'included' => (bool) $this->settings->get('sitemap_include_blog'),
                    'reason' => $this->settings->get('sitemap_include_blog') ? null : 'Artigos desativados nas preferências de sitemap',
                    'editUrl' => '/admin/blog/categories',
                    'sitemap' => ['loc' => $this->settings->url('/blog?category='.$category->slug), 'changefreq' => 'weekly', 'priority' => '0.5'],
                ]));
        }

        return $entries;
    }

    /**
     * @param  array<string, mixed>  $page
     * @param  array<string, string|null>  $sitemap
     * @return array{source: string, label: string, url: string, included: bool, reason: string|null, editUrl: string|null, sitemap: array<string, string|null>}
     */
    private function entry(string $source, array $page, array $sitemap): array
    {
        $reason = match (true) {
            ! Seo::isIndexable($page['effectiveRobots']) => 'Marcada como noindex',
            $page['canonicalElsewhere'] => 'Canonical aponta para '.$page['effectiveCanonical'],
            default => null,
        };

        return [
            'source' => $source,
            'label' => $page['label'],
            'url' => $page['url'],
            'included' => $reason === null,
            'reason' => $reason,
            'editUrl' => $page['editUrl'],
            'sitemap' => $sitemap,
        ];
    }
}
