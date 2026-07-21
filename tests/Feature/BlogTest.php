<?php

use App\Models\BlogCategory;
use App\Models\BlogComment;
use App\Models\BlogPost;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('published posts are public and drafts are hidden', function () {
    $admin = User::factory()->create();
    $published = BlogPost::create(['user_id' => $admin->id, 'title' => 'Artigo público', 'slug' => 'artigo-publico', 'excerpt' => 'Resumo', 'body' => 'Conteúdo', 'status' => 'published', 'published_at' => now()]);
    $draft = BlogPost::create(['user_id' => $admin->id, 'title' => 'Rascunho', 'slug' => 'rascunho', 'excerpt' => 'Resumo', 'body' => 'Conteúdo', 'status' => 'draft']);

    $this->get(route('blog.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('blog/index')
        ->where('posts.data.0.title', 'Artigo público')
        ->has('posts.data', 1));
    $this->get(route('blog.show', $published))->assertOk();
    $this->get(route('blog.show', $draft))->assertNotFound();
});

test('reader can comment but cannot access administration', function () {
    $admin = User::factory()->create();
    $reader = User::factory()->reader()->create();
    $post = BlogPost::create(['user_id' => $admin->id, 'title' => 'Teste', 'slug' => 'teste', 'excerpt' => 'Resumo', 'body' => 'Conteúdo', 'status' => 'published', 'published_at' => now()]);

    $this->actingAs($reader)->post(route('blog.comments.store', $post), ['body' => 'Ótimo conteúdo!'])->assertRedirect();
    expect(BlogComment::first())->status->toBe('pending');
    $this->actingAs($reader)->get(route('admin.blog.posts.index'))->assertForbidden();
    $this->actingAs($reader)->get(route('leads.index'))->assertForbidden();
});

test('administrator can create article and moderate comment', function () {
    $admin = User::factory()->create();
    $reader = User::factory()->reader()->create();
    $category = BlogCategory::create(['name' => 'Tecnologia', 'slug' => 'tecnologia']);

    $this->actingAs($admin)->post(route('admin.blog.posts.store'), ['title' => 'Novo artigo', 'excerpt' => 'Um resumo do artigo', 'body' => 'Conteúdo completo', 'blog_category_id' => $category->id, 'cover_image_url' => null, 'status' => 'published', 'published_at' => null, 'featured' => true])->assertRedirect(route('admin.blog.posts.index'));
    $post = BlogPost::firstOrFail();
    $comment = BlogComment::create(['blog_post_id' => $post->id, 'user_id' => $reader->id, 'body' => 'Comentário', 'status' => 'pending']);
    $this->patch(route('admin.blog.comments.approve', $comment))->assertRedirect();
    expect($comment->fresh()->status)->toBe('approved');
});
