<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlogCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BlogCategoryController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/blog/categories', ['categories' => BlogCategory::withCount('posts')->orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100', 'unique:blog_categories,name'], 'description' => ['nullable', 'string', 'max:500']]);
        BlogCategory::create([...$data, 'slug' => $this->uniqueSlug($data['name'])]);

        return back();
    }

    public function update(Request $request, BlogCategory $category): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100', Rule::unique('blog_categories')->ignore($category)], 'description' => ['nullable', 'string', 'max:500']]);
        $category->update([...$data, 'slug' => $this->uniqueSlug($data['name'], $category->id)]);

        return back();
    }

    public function destroy(BlogCategory $category): RedirectResponse
    {
        $category->delete();

        return back();
    }

    private function uniqueSlug(string $name, ?int $except = null): string
    {
        $base = Str::slug($name) ?: 'categoria';
        $slug = $base;
        $suffix = 2;
        while (BlogCategory::where('slug', $slug)->when($except, fn ($query) => $query->whereKeyNot($except))->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
