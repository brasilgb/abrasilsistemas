<?php

namespace App\Http\Controllers;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PublicBlogController extends Controller
{
    public function index(Request $request): Response
    {
        $posts = BlogPost::query()
            ->published()
            ->with(['category:id,name,slug', 'author:id,name'])
            ->when($request->string('category')->isNotEmpty(), fn ($query) => $query->whereHas('category', fn ($category) => $category->where('slug', $request->string('category'))))
            ->when($request->string('search')->isNotEmpty(), fn ($query) => $query->where(function ($query) use ($request) {
                $search = '%'.$request->string('search')->trim().'%';
                $query->where('title', 'like', $search)->orWhere('excerpt', 'like', $search);
            }))
            ->latest('published_at')
            ->paginate(9)
            ->withQueryString();

        return Inertia::render('blog/index', [
            'posts' => $posts,
            'categories' => BlogCategory::query()
                ->whereHas('posts', fn ($query) => $query->where('status', 'published')->where('published_at', '<=', now()))
                ->withCount(['posts' => fn ($query) => $query->where('status', 'published')->where('published_at', '<=', now())])
                ->orderBy('name')->get(),
            'popularPosts' => BlogPost::query()->published()->orderByDesc('views')->limit(5)->get(['id', 'title', 'slug', 'views', 'published_at']),
            'filters' => $request->only(['category', 'search']),
        ]);
    }

    public function show(BlogPost $post): Response
    {
        abort_unless($post->status === 'published' && $post->published_at?->isPast(), 404);
        $post->increment('views');
        $post->load(['category:id,name,slug', 'author:id,name', 'comments' => fn ($query) => $query->where('status', 'approved')->with('user:id,name')->oldest()]);

        return Inertia::render('blog/show', [
            'post' => $post,
            'relatedPosts' => BlogPost::query()->published()->whereKeyNot($post)->when($post->blog_category_id, fn ($query) => $query->where('blog_category_id', $post->blog_category_id))->latest('published_at')->limit(3)->get(['id', 'title', 'slug', 'excerpt', 'published_at']),
        ]);
    }
}
