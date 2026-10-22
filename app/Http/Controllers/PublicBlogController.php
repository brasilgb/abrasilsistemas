<?php

namespace App\Http\Controllers;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\BlogTag;
use App\Services\Seo\SeoSettings;
use App\Services\Seo\StructuredData;
use App\Support\PublicPages;
use App\Support\Seo;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PublicBlogController extends Controller
{
    public function index(Request $request): Response
    {
        $posts = BlogPost::query()
            ->published()
            ->with(['category:id,name,slug', 'author:id,name', 'tags:id,name,slug'])
            ->when($request->string('category')->isNotEmpty(), fn ($query) => $query->whereHas('category', fn ($category) => $category->where('slug', $request->string('category'))))
            ->when($request->string('tag')->isNotEmpty(), fn ($query) => $query->whereHas('tags', fn ($tag) => $tag->where('slug', $request->string('tag'))))
            ->when($request->string('search')->isNotEmpty(), fn ($query) => $query->where(function ($query) use ($request) {
                $search = '%'.$request->string('search')->trim().'%';
                $query->where('title', 'like', $search)->orWhere('excerpt', 'like', $search);
            }))
            ->latest('published_at')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('blog/index', [
            'posts' => $posts,
            'categories' => BlogCategory::query()
                ->whereHas('posts', fn ($query) => $query->where('status', 'published')->where('published_at', '<=', now()))
                ->withCount(['posts' => fn ($query) => $query->where('status', 'published')->where('published_at', '<=', now())])
                ->orderBy('name')->get(),
            'tags' => BlogTag::query()
                ->whereHas('posts', fn ($query) => $query->where('status', 'published')->where('published_at', '<=', now()))
                ->withCount(['posts' => fn ($query) => $query->where('status', 'published')->where('published_at', '<=', now())])
                ->orderBy('name')->get(),
            'popularPosts' => BlogPost::query()->published()->orderByDesc('views')->limit(5)->get(['id', 'title', 'slug', 'views', 'published_at']),
            'filters' => $request->only(['category', 'tag', 'search']),
            'seo' => $this->indexSeo($request, $posts->currentPage()),
        ]);
    }

    public function show(BlogPost $post): Response
    {
        abort_unless($post->status === 'published' && $post->published_at?->isPast(), 404);
        $post->increment('views');
        $post->load(['category:id,name,slug', 'author:id,name', 'tags:id,name,slug', 'comments' => fn ($query) => $query->where('status', 'approved')->with('user:id,name')->oldest()]);

        return Inertia::render('blog/show', [
            'post' => $post,
            'relatedPosts' => BlogPost::query()->published()->whereKeyNot($post)->when($post->blog_category_id, fn ($query) => $query->where('blog_category_id', $post->blog_category_id))->latest('published_at')->limit(3)->get(['id', 'title', 'slug', 'excerpt', 'published_at']),
            'seo' => Seo::post($post),
        ]);
    }

    /**
     * Política de indexação da listagem: busca e tags não são indexadas (conteúdo repetido),
     * a categoria tem canonical próprio e as páginas seguintes da paginação apontam para si
     * mesmas. Qualquer outro parâmetro de query sai do canonical.
     *
     * @return array<string, mixed>
     */
    private function indexSeo(Request $request, int $page): array
    {
        $query = array_filter([
            'category' => $request->string('category')->trim()->toString() ?: null,
            'page' => $page > 1 ? $page : null,
        ]);
        $noindex = $request->string('search')->isNotEmpty() || $request->string('tag')->isNotEmpty();
        $category = isset($query['category']) ? BlogCategory::query()->where('slug', $query['category'])->first() : null;

        // Categoria inexistente não vira uma URL canônica nova.
        if (isset($query['category']) && $category === null) {
            unset($query['category']);
            $noindex = true;
        }

        $settings = app(SeoSettings::class);
        $jsonLd = ['@context' => 'https://schema.org', '@graph' => [
            PublicPages::organization(),
            ['@type' => 'Blog', 'name' => 'Blog da '.$settings->get('company_name'), 'url' => StructuredData::url('/blog'), 'publisher' => ['@id' => StructuredData::organizationId()]],
            StructuredData::breadcrumbs(array_values(array_filter([['Blog', '/blog'], $category ? [$category->name, '/blog?category='.$category->slug] : null]))),
        ]];

        // A listagem principal usa os metadados do painel; filtros e páginas seguintes derivam dela.
        if ($query === [] && ! $noindex) {
            return Seo::page('blog', ['jsonLd' => $jsonLd]);
        }

        $base = Seo::page('blog');
        $title = trim(($category ? $category->name.' — ' : '').'Blog'.($page > 1 ? " (página {$page})" : ''));

        return Seo::tags([
            'title' => $settings->formatTitle($title),
            'description' => $category?->description ?: $base['description'],
            'canonical' => '/blog'.($query ? '?'.http_build_query($query) : ''),
            'robots' => $noindex ? 'noindex, follow' : $base['robots'],
            'jsonLd' => $jsonLd,
        ]);
    }
}
