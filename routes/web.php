<?php

use App\Http\Controllers\Admin\BlogCategoryController as AdminBlogCategoryController;
use App\Http\Controllers\Admin\BlogCommentController as AdminBlogCommentController;
use App\Http\Controllers\Admin\BlogImageController as AdminBlogImageController;
use App\Http\Controllers\Admin\BlogPostController as AdminBlogPostController;
use App\Http\Controllers\Admin\PortfolioImageController;
use App\Http\Controllers\Admin\PortfolioItemController;
use App\Http\Controllers\Admin\TestimonialController;
use App\Http\Controllers\Admin\TestimonialImageController;
use App\Http\Controllers\BlogCommentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EbookMercadoPagoWebhookController;
use App\Http\Controllers\LeadActivityController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\PublicBlogController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\UserController;
use App\Models\BlogPost;
use App\Models\PortfolioItem;
use App\Models\Testimonial;
use App\Support\Seo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome', [
        'blogPosts' => BlogPost::query()->published()->with('category:id,name,slug')->latest('published_at')->limit(3)->get(['id', 'blog_category_id', 'title', 'slug', 'excerpt', 'published_at']),
        'testimonials' => Testimonial::query()->published()->orderBy('sort_order')->latest()->limit(9)->get(['id', 'author_name', 'author_role', 'photo_url', 'quote']),
        'portfolioItems' => PortfolioItem::query()->published()->orderBy('sort_order')->latest()->limit(6)->get(['id', 'title', 'description', 'screenshot_url', 'site_url']),
        'seo' => Seo::tags([
            'title' => 'Sistemas, sites e software sob medida | ABrasil Sistemas',
            'description' => 'A ABrasil Sistemas desenvolve o VetorOS, o VetorPet, sistemas sob medida e sites profissionais para empresas.',
            'canonical' => url('/'),
            'ogTitle' => 'ABrasil Sistemas — Produtos digitais e desenvolvimento',
            'ogDescription' => 'VetorOS, VetorPet, desenvolvimento de sistemas sob medida e criação de sites profissionais.',
        ]),
    ]);
})->name('home');
Route::get('desenvolvimento-de-sites-para-empresas', function () {
    return Inertia::render('company-websites', [
        'seo' => Seo::tags([
            'title' => 'Desenvolvimento de sites para empresas | ABrasil Sistemas',
            'description' => 'Desenvolvimento de sites profissionais para empresas. Sites rápidos, responsivos, preparados para o Google e focados em gerar novos contatos.',
            'canonical' => url('/desenvolvimento-de-sites-para-empresas'),
            'ogTitle' => 'Desenvolvimento de sites para empresas | ABrasil Sistemas',
            'ogDescription' => 'Um site rápido, profissional e feito para gerar oportunidades para sua empresa.',
        ]),
    ]);
})->name('company-websites');
Route::get('sitemap.xml', SitemapController::class)->name('sitemap');
Route::get('blog', [PublicBlogController::class, 'index'])->name('blog.index');
Route::get('blog/{post:slug}', [PublicBlogController::class, 'show'])->name('blog.show');
Route::get('area-restrita', function (Request $request) {
    if (! $request->user()) {
        return redirect()->guest(route('login'));
    }

    if (! $request->user()->isAdmin()) {
        Inertia::flash('toast', [
            'type' => 'error',
            'message' => 'Você não possui acesso à área restrita.',
        ]);

        return to_route('blog.index');
    }

    return to_route('dashboard');
})->name('restricted-area');
Route::redirect('conhecimento', '/blog', 301);
Route::redirect('conhecimento/{path}', '/blog', 301)->where('path', '.*');
Route::redirect('minha-biblioteca', '/blog', 301);
Route::post('webhooks/mercadopago/ebooks/{token}', EbookMercadoPagoWebhookController::class)->name('webhooks.mercadopago.ebooks');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('blog/{post:slug}/comentarios', [BlogCommentController::class, 'store'])->name('blog.comments.store');
    Route::middleware('admin')->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');
        Route::redirect('admin', '/dashboard')->name('admin');
        Route::post('leads/import', [LeadController::class, 'import'])->name('leads.import');
        Route::delete('leads/clear', [LeadController::class, 'clear'])->name('leads.clear');
        Route::patch('leads/bulk', [LeadController::class, 'bulkUpdate'])->name('leads.bulk');
        Route::patch('leads/{lead}/status', [LeadController::class, 'status'])->name('leads.status');
        Route::resource('leads', LeadController::class)->only(['index', 'create', 'store', 'edit', 'update', 'destroy']);
        Route::post('leads/{lead}/activities', [LeadActivityController::class, 'store'])->name('leads.activities.store');
        Route::resource('users', UserController::class)->only(['index', 'create', 'store', 'edit', 'update']);

        Route::post('testimonials/images', TestimonialImageController::class)->name('testimonials.images.store');
        Route::resource('testimonials', TestimonialController::class)->except('show');

        Route::post('portfolio/images', PortfolioImageController::class)->name('portfolio.images.store');
        Route::resource('portfolio', PortfolioItemController::class)->except('show');

        Route::prefix('admin/blog')->name('admin.blog.')->group(function () {
            Route::post('images', AdminBlogImageController::class)->name('images.store');
            Route::resource('posts', AdminBlogPostController::class)->except('show');
            Route::resource('categories', AdminBlogCategoryController::class)->only(['index', 'store', 'update', 'destroy']);
            Route::get('comments', [AdminBlogCommentController::class, 'index'])->name('comments.index');
            Route::patch('comments/{comment}/approve', [AdminBlogCommentController::class, 'approve'])->name('comments.approve');
            Route::patch('comments/{comment}/reject', [AdminBlogCommentController::class, 'reject'])->name('comments.reject');
            Route::delete('comments/{comment}', [AdminBlogCommentController::class, 'destroy'])->name('comments.destroy');
        });
    });
});

require __DIR__.'/settings.php';
