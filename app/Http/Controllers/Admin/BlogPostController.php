<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlogCategory;
use App\Models\BlogComment;
use App\Models\BlogPost;
use App\Models\BlogTag;
use App\Models\SeoMeta;
use App\Models\User;
use App\Services\Seo\ContentAnalyzer;
use App\Services\Seo\IndexNow;
use App\Services\Seo\PageRegistry;
use App\Services\Seo\RedirectManager;
use App\Services\Seo\SeoMetaStore;
use App\Services\Seo\SeoSettings;
use App\Support\HtmlSanitizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BlogPostController extends Controller
{
    /** Slug: minúsculas, números e hífens (ex.: como-automatizar-cobrancas). */
    public const SLUG_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    public function __construct(
        private SeoMetaStore $seoStore,
        private RedirectManager $redirects,
        private ContentAnalyzer $analyzer,
        private IndexNow $indexNow,
    ) {}

    public function index(): Response
    {
        $posts = BlogPost::query()->with(['category:id,name', 'author:id,name'])->latest()->paginate(15);
        $scores = SeoMeta::query()->where('subject_type', SeoMeta::TYPE_POST)->whereIn('subject_id', $posts->getCollection()->pluck('id')->map(fn ($id) => (string) $id))->pluck('editorial_score', 'subject_id');
        $posts->getCollection()->each(fn (BlogPost $post) => $post->setAttribute('seo_score', $scores[(string) $post->id] ?? null));

        return Inertia::render('admin/blog/index', [
            'posts' => $posts,
            'metrics' => ['posts' => BlogPost::count(), 'published' => BlogPost::published()->count(), 'pendingComments' => BlogComment::where('status', 'pending')->count(), 'users' => User::count()],
        ]);
    }

    public function create(SeoSettings $settings): Response
    {
        return Inertia::render('admin/blog/form', [
            'categories' => BlogCategory::orderBy('name')->get(),
            'existingTags' => BlogTag::orderBy('name')->pluck('name'),
            'post' => null,
            ...$this->seoProps(null, $settings),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['body'] = HtmlSanitizer::clean($data['body']);
        $slug = $this->uniqueSlug(filled($data['slug'] ?? null) ? $data['slug'] : $data['title']);
        $post = BlogPost::create([
            ...Arr::except($data, ['slug', 'seo', 'create_redirect']),
            'user_id' => $request->user()->id,
            'slug' => $slug,
            'published_at' => $data['status'] === 'published' ? ($data['published_at'] ?: now()) : null,
        ]);
        $post->tags()->sync($this->tagIds($data['tags'] ?? []));
        $this->saveSeo($post, $data['seo'] ?? [], $request->user()->id);
        $this->notifyIndexNow($post);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Artigo criado.']);

        return to_route('admin.blog.posts.index');
    }

    public function edit(BlogPost $post, SeoSettings $settings): Response
    {
        return Inertia::render('admin/blog/form', [
            'categories' => BlogCategory::orderBy('name')->get(),
            'existingTags' => BlogTag::orderBy('name')->pluck('name'),
            'post' => $post->load('tags:id,name'),
            ...$this->seoProps($post, $settings),
        ]);
    }

    public function update(Request $request, BlogPost $post): RedirectResponse
    {
        $data = $this->validated($request, $post);
        $data['body'] = HtmlSanitizer::clean($data['body']);
        $wasPublic = $post->status === 'published' && $post->published_at?->isPast();
        $oldSlug = $post->slug;

        // O slug só muda quando editado explicitamente: trocar o título não quebra a URL publicada.
        $slug = filled($data['slug'] ?? null) && $data['slug'] !== $oldSlug ? $this->uniqueSlug($data['slug'], $post->id) : $oldSlug;

        $post->update([
            ...Arr::except($data, ['slug', 'seo', 'create_redirect']),
            'slug' => $slug,
            'published_at' => $data['status'] === 'published' ? ($data['published_at'] ?: $post->published_at ?: now()) : null,
        ]);
        $post->tags()->sync($this->tagIds($data['tags'] ?? []));
        $this->saveSeo($post, $data['seo'] ?? [], $request->user()->id);

        $message = 'Artigo atualizado.';

        if ($slug !== $oldSlug && $wasPublic && ($data['create_redirect'] ?? true)) {
            $this->redirects->redirectMovedPath('/blog/'.$oldSlug, '/blog/'.$slug, $request->user()->id);
            $message .= ' A URL antiga redireciona (301) para a nova.';
        }

        $this->notifyIndexNow($post);
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return to_route('admin.blog.posts.index');
    }

    public function destroy(Request $request, BlogPost $post): RedirectResponse
    {
        $wasPublic = $post->status === 'published' && $post->published_at?->isPast();
        $path = '/blog/'.$post->slug;
        $post->delete();
        SeoMeta::query()->where('subject_type', SeoMeta::TYPE_POST)->where('subject_id', (string) $post->id)->delete();

        // Artigo publicado removido: a URL passa a responder 410 (removida), preservando o histórico.
        if ($wasPublic) {
            $this->redirects->markGone($path, $request->user()->id, 'Artigo excluído: "'.Str::limit($post->title, 120).'". Troque para 301 se houver um conteúdo substituto.');
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $wasPublic ? 'Artigo excluído. A URL responde 410 (removida); em SEO → Redirecionamentos você pode apontá-la para outro conteúdo.' : 'Artigo excluído.']);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function seoProps(?BlogPost $post, SeoSettings $settings): array
    {
        $registry = app(PageRegistry::class);

        return [
            'seo' => $post ? SeoMeta::for(SeoMeta::TYPE_POST, $post->id) : null,
            'seoContext' => [
                'siteName' => $settings->get('company_name'),
                'titleTemplate' => $settings->get('title_template'),
                'baseUrl' => $settings->baseUrl(),
                'defaultImage' => $settings->absolute($settings->get('social_image_url')),
                'intents' => SeoMeta::INTENTS,
                'pageOptions' => $registry->all()->reject(fn ($page) => $post && $page['key'] === SeoMeta::subjectKey(SeoMeta::TYPE_POST, $post->id))
                    ->map(fn ($page) => ['key' => $page['key'], 'label' => $page['label'], 'group' => $page['group']])->values(),
                'isPublished' => $post !== null && $post->status === 'published' && $post->published_at?->isPast(),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $seo
     */
    private function saveSeo(BlogPost $post, array $seo, int $userId): void
    {
        $analysis = $this->analyzer->analyze([
            'title' => $post->title,
            'meta_title' => $seo['meta_title'] ?? null,
            'meta_description' => $seo['meta_description'] ?? null,
            'excerpt' => $post->excerpt,
            'slug' => $post->slug,
            'body' => (string) $post->body,
            'focus_keyword' => $seo['focus_keyword'] ?? null,
            'post_id' => $post->id,
        ]);

        $this->seoStore->save(SeoMeta::TYPE_POST, $post->id, $seo, $userId, $analysis['score']);
    }

    private function notifyIndexNow(BlogPost $post): void
    {
        if ($post->status === 'published' && $post->published_at?->isPast() && $this->indexNow->enabled()) {
            $url = app(SeoSettings::class)->url('/blog/'.$post->slug);
            dispatch(fn () => app(IndexNow::class)->submit([$url]))->afterResponse();
        }
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?BlogPost $post = null): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:180'], 'excerpt' => ['required', 'string', 'max:500'], 'body' => ['required', 'string'],
            'slug' => ['nullable', 'string', 'max:180', 'regex:'.self::SLUG_PATTERN, Rule::unique('blog_posts', 'slug')->ignore($post?->id)],
            'blog_category_id' => ['nullable', 'integer', 'exists:blog_categories,id'], 'cover_image_url' => ['nullable', 'url', 'max:2048'],
            'status' => ['required', Rule::in(['draft', 'published'])], 'published_at' => ['nullable', 'date'], 'featured' => ['boolean'],
            'tags' => ['sometimes', 'array'], 'tags.*' => ['string', 'max:50'],
            'create_redirect' => ['sometimes', 'boolean'],
            'seo' => ['sometimes', 'array'],
            ...SeoMetaStore::rules('seo.'),
        ], [
            'slug.regex' => 'Use só letras minúsculas sem acento, números e hífens (ex.: como-automatizar-cobrancas).',
            'slug.unique' => 'Outro artigo já usa esse endereço.',
        ]);
    }

    /**
     * Find or create a BlogTag for each name and return their ids, ready for sync().
     *
     * @param  list<string>  $names
     * @return list<int>
     */
    private function tagIds(array $names): array
    {
        return collect($names)
            ->map(fn (string $name) => trim($name))
            ->filter()
            ->unique(fn (string $name) => Str::lower($name))
            ->map(fn (string $name) => BlogTag::query()->firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name],
            )->id)
            ->values()
            ->all();
    }

    private function uniqueSlug(string $value, ?int $except = null): string
    {
        $base = Str::limit(Str::slug($value), 180, '') ?: 'artigo';
        $slug = $base;
        $suffix = 2;
        while (BlogPost::where('slug', $slug)->when($except, fn ($query) => $query->whereKeyNot($except))->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
