<?php

use App\Http\Controllers\Admin\BlogCategoryController as AdminBlogCategoryController;
use App\Http\Controllers\Admin\BlogCommentController as AdminBlogCommentController;
use App\Http\Controllers\Admin\BlogPostController as AdminBlogPostController;
use App\Http\Controllers\BlogCommentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EbookDownloadController;
use App\Http\Controllers\EbookLibraryController;
use App\Http\Controllers\EbookMercadoPagoWebhookController;
use App\Http\Controllers\EbookOrderStatusController;
use App\Http\Controllers\EbookPixCheckoutController;
use App\Http\Controllers\KnowledgeVolumeController;
use App\Http\Controllers\LeadActivityController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\PublicBlogController;
use App\Http\Controllers\UserController;
use App\Models\BlogPost;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome', [
        'blogPosts' => BlogPost::query()->published()->with('category:id,name,slug')->latest('published_at')->limit(3)->get(['id', 'blog_category_id', 'title', 'slug', 'excerpt', 'published_at']),
    ]);
})->name('home');
Route::get('blog', [PublicBlogController::class, 'index'])->name('blog.index');
Route::get('blog/{post:slug}', [PublicBlogController::class, 'show'])->name('blog.show');
Route::inertia('conhecimento', 'knowledge')->name('knowledge');
Route::get('conhecimento/volumes/{volume}', KnowledgeVolumeController::class)->name('knowledge.volumes.show');
Route::post('webhooks/mercadopago/ebooks/{token}', EbookMercadoPagoWebhookController::class)->name('webhooks.mercadopago.ebooks');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('blog/{post:slug}/comentarios', [BlogCommentController::class, 'store'])->name('blog.comments.store');
    Route::get('minha-biblioteca', EbookLibraryController::class)->name('ebooks.library');
    Route::get('minha-biblioteca/{ebook}/download', EbookDownloadController::class)->name('ebooks.download');
    Route::post('conhecimento/ebooks/{ebook}/pix', EbookPixCheckoutController::class)->name('ebooks.pix.store');
    Route::get('conhecimento/pedidos/{order}/status', EbookOrderStatusController::class)->name('ebooks.orders.status');

    Route::middleware('admin')->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');
        Route::redirect('admin', '/dashboard')->name('admin');
        Route::post('leads/import', [LeadController::class, 'import'])->name('leads.import');
        Route::delete('leads/clear', [LeadController::class, 'clear'])->name('leads.clear');
        Route::patch('leads/{lead}/status', [LeadController::class, 'status'])->name('leads.status');
        Route::resource('leads', LeadController::class)->only(['index', 'create', 'store', 'edit', 'update', 'destroy']);
        Route::post('leads/{lead}/activities', [LeadActivityController::class, 'store'])->name('leads.activities.store');
        Route::resource('users', UserController::class)->only(['index', 'create', 'store', 'edit', 'update']);

        Route::prefix('admin/blog')->name('admin.blog.')->group(function () {
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
