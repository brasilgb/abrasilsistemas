<?php

namespace App\Services\Seo;

use App\Models\BlogPost;
use App\Models\SeoMeta;
use App\Support\PublicPages;
use App\Support\Seo;
use Illuminate\Support\Collection;

/**
 * Lista única das páginas públicas que o SEO administra: as institucionais (fixas, em
 * PublicPages), as de serviço, as de produto e os artigos publicados do blog. Cada
 * página tem uma chave estável ("page:sobre", "page:servico:automacao-de-processos",
 * "post:12"), usada pelos metadados, pela auditoria e pelos links do painel.
 */
class PageRegistry
{
    /**
     * @return Collection<int, array{key: string, type: string, id: string, label: string, group: string, path: string, title: string, description: string, robots: string, ogImage: string|null, ogImageAlt: string|null, editUrl: string, contentUrl: string|null, publishedAt: string|null}>
     */
    public function all(bool $withPosts = true): Collection
    {
        $pages = collect($this->institutional());

        if ($withPosts) {
            $pages = $pages->concat($this->posts());
        }

        return $pages->values();
    }

    /**
     * @return array{key: string, type: string, id: string, label: string, group: string, path: string, title: string, description: string, robots: string, ogImage: string|null, ogImageAlt: string|null, editUrl: string, contentUrl: string|null, publishedAt: string|null}|null
     */
    public function find(string $key): ?array
    {
        [$type, $id] = array_pad(explode(':', $key, 2), 2, '');

        if ($type === SeoMeta::TYPE_POST) {
            $post = BlogPost::query()->find((int) $id);

            return $post ? $this->postEntry($post) : null;
        }

        return collect($this->institutional())->firstWhere('key', $key);
    }

    /**
     * Página correspondente a um caminho público ("/sobre", "/blog/slug").
     *
     * @return array<string, mixed>|null
     */
    public function findByPath(string $path): ?array
    {
        $path = '/'.trim(parse_url($path, PHP_URL_PATH) ?: '/', '/');

        if (str_starts_with($path, '/blog/')) {
            $post = BlogPost::query()->where('slug', substr($path, 6))->first();

            return $post ? $this->postEntry($post) : null;
        }

        return collect($this->institutional())->firstWhere('path', $path);
    }

    /**
     * @return list<array{key: string, type: string, id: string, label: string, group: string, path: string, title: string, description: string, robots: string, ogImage: string|null, ogImageAlt: string|null, editUrl: string, contentUrl: string|null, publishedAt: string|null}>
     */
    private function institutional(): array
    {
        $entries = [];

        foreach (PublicPages::PAGES as $id => $page) {
            $entries[] = $this->entry($id, $page['label'], $page['group'], $page['path'], $page['title'], $page['description'], $page['robots'] ?? null, $page['hero'] ?? null);
        }

        foreach (PublicPages::SERVICES as $slug => $service) {
            $entries[] = $this->entry('servico:'.$slug, $service['serviceType'], 'Serviços', '/'.$slug, $service['title'], $service['description'], null, PublicPages::SERVICE_HERO_IMAGES[$slug]);
        }

        foreach (PublicPages::PRODUCTS as $slug => $product) {
            $entries[] = $this->entry('sistema:'.$slug, $product['name'], 'Produtos', '/sistemas/'.$slug, $product['title'], $product['description'], null, $slug);
        }

        return $entries;
    }

    /**
     * @return array{key: string, type: string, id: string, label: string, group: string, path: string, title: string, description: string, robots: string, ogImage: string|null, ogImageAlt: string|null, editUrl: string, contentUrl: string|null, publishedAt: string|null}
     */
    private function entry(string $id, string $label, string $group, string $path, string $title, string $description, ?string $robots, ?string $hero): array
    {
        $key = SeoMeta::subjectKey(SeoMeta::TYPE_PAGE, $id);

        return [
            'key' => $key,
            'type' => SeoMeta::TYPE_PAGE,
            'id' => $id,
            'label' => $label,
            'group' => $group,
            'path' => $path,
            'title' => $title,
            'description' => $description,
            'robots' => $robots ?? Seo::DEFAULT_ROBOTS,
            'ogImage' => $hero ? '/images/site/heroes/'.$hero.'-og.jpg' : null,
            'ogImageAlt' => $hero ? PublicPages::HERO_IMAGES[$hero] : null,
            'editUrl' => '/admin/seo/paginas/'.$key,
            'contentUrl' => null,
            'publishedAt' => null,
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function posts(): Collection
    {
        return BlogPost::query()->published()->latest('published_at')->get()->map(fn (BlogPost $post) => $this->postEntry($post));
    }

    /**
     * @return array{key: string, type: string, id: string, label: string, group: string, path: string, title: string, description: string, robots: string, ogImage: string|null, ogImageAlt: string|null, editUrl: string, contentUrl: string|null, publishedAt: string|null}
     */
    public function postEntry(BlogPost $post): array
    {
        $key = SeoMeta::subjectKey(SeoMeta::TYPE_POST, $post->id);
        $published = $post->status === 'published' && $post->published_at?->isPast();

        return [
            'key' => $key,
            'type' => SeoMeta::TYPE_POST,
            'id' => (string) $post->id,
            'label' => $post->title,
            'group' => 'Blog',
            'path' => '/blog/'.$post->slug,
            'title' => app(SeoSettings::class)->formatTitle($post->title),
            'description' => (string) $post->excerpt,
            // Rascunho e agendado nunca são indexáveis, mesmo com index explícito.
            'robots' => $published ? Seo::DEFAULT_ROBOTS : 'noindex, nofollow',
            'ogImage' => $post->cover_image_url ?: null,
            'ogImageAlt' => null,
            'editUrl' => '/admin/blog/posts/'.$post->id.'/edit#seo',
            'contentUrl' => '/admin/blog/posts/'.$post->id.'/edit',
            'publishedAt' => $post->published_at?->toAtomString(),
        ];
    }

    /**
     * Valores efetivos (o que vai para o HTML) de cada página, com os metadados do painel
     * aplicados. Não aplica a política de host: mostra o resultado no domínio canônico.
     *
     * @param  Collection<int, array<string, mixed>>|null  $pages
     * @return Collection<int, array<string, mixed>>
     */
    public function withEffective(?Collection $pages = null): Collection
    {
        $pages ??= $this->all();
        $metas = SeoMeta::query()->get()->keyBy(fn (SeoMeta $meta) => SeoMeta::subjectKey($meta->subject_type, $meta->subject_id));
        $settings = app(SeoSettings::class);

        return $pages->map(function (array $page) use ($metas, $settings) {
            $meta = $metas->get($page['key']);
            $tags = Seo::applyMeta([
                'title' => $page['title'],
                'description' => $page['description'],
                'canonical' => $page['path'],
                'robots' => $page['robots'],
                'ogImage' => $page['ogImage'],
            ], $meta);
            $canonical = Seo::canonicalize((string) $tags['canonical']);
            $own = $settings->url($page['path']);

            return [
                ...$page,
                'url' => $own,
                'effectiveTitle' => (string) ($tags['title'] ?? ''),
                'effectiveDescription' => (string) ($tags['description'] ?? ''),
                'effectiveRobots' => (string) $tags['robots'],
                'effectiveCanonical' => $canonical,
                'ogImage' => $tags['ogImage'] ?? null,
                'indexable' => Seo::isIndexable($tags['robots']) && $canonical === $own,
                'canonicalElsewhere' => $canonical !== $own,
                'meta' => $meta,
                'focusKeyword' => $meta?->focus_keyword,
            ];
        });
    }
}
