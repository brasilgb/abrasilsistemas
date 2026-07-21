<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlogCategory;
use App\Models\BlogComment;
use App\Models\BlogPost;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BlogPostController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/blog/index', [
            'posts' => BlogPost::query()->with(['category:id,name', 'author:id,name'])->latest()->paginate(15),
            'metrics' => ['posts' => BlogPost::count(), 'published' => BlogPost::published()->count(), 'pendingComments' => BlogComment::where('status', 'pending')->count(), 'users' => User::count()],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/blog/form', ['categories' => BlogCategory::orderBy('name')->get(), 'post' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        BlogPost::create([...$data, 'user_id' => $request->user()->id, 'slug' => $this->uniqueSlug($data['title']), 'published_at' => $data['status'] === 'published' ? ($data['published_at'] ?: now()) : null]);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Artigo criado.']);

        return to_route('admin.blog.posts.index');
    }

    public function edit(BlogPost $post): Response
    {
        return Inertia::render('admin/blog/form', ['categories' => BlogCategory::orderBy('name')->get(), 'post' => $post]);
    }

    public function update(Request $request, BlogPost $post): RedirectResponse
    {
        $data = $this->validated($request);
        $post->update([...$data, 'slug' => $this->uniqueSlug($data['title'], $post->id), 'published_at' => $data['status'] === 'published' ? ($data['published_at'] ?: $post->published_at ?: now()) : null]);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Artigo atualizado.']);

        return to_route('admin.blog.posts.index');
    }

    public function destroy(BlogPost $post): RedirectResponse
    {
        $post->delete();
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Artigo excluído.']);

        return back();
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:180'], 'excerpt' => ['required', 'string', 'max:500'], 'body' => ['required', 'string'],
            'blog_category_id' => ['nullable', 'integer', 'exists:blog_categories,id'], 'cover_image_url' => ['nullable', 'url', 'max:2048'],
            'status' => ['required', Rule::in(['draft', 'published'])], 'published_at' => ['nullable', 'date'], 'featured' => ['boolean'],
        ]);
    }

    private function uniqueSlug(string $title, ?int $except = null): string
    {
        $base = Str::slug($title) ?: 'artigo';
        $slug = $base;
        $suffix = 2;
        while (BlogPost::where('slug', $slug)->when($except, fn ($query) => $query->whereKeyNot($except))->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
