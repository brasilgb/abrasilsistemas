<?php

namespace App\Http\Controllers;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\BlogTag;
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
            'seo' => Seo::tags([
                'title' => 'Blog sobre tecnologia e gestão | ABrasil Sistemas',
                'description' => 'Informação prática para usar a tecnologia a favor da sua empresa.',
                'canonical' => url('/blog'),
                'ogTitle' => 'Blog | ABrasil Sistemas',
                'ogDescription' => 'Informação prática para usar a tecnologia a favor da sua empresa.',
            ]),
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
            'seo' => Seo::tags([
                'title' => $post->title.' | ABrasil Sistemas',
                'description' => $post->excerpt,
                'canonical' => url('/blog/'.$post->slug),
                'ogType' => 'article',
                'ogTitle' => $post->title,
                'ogDescription' => $post->excerpt,
                'ogImage' => $post->cover_image_url ?: url('/images/dashboard-vetoros.webp'),
            ]),
        ]);
    }
}
