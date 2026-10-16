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
use App\Http\Controllers\ContactController;
use App\Http\Controllers\EbookMercadoPagoWebhookController;
use App\Http\Controllers\PublicBlogController;
use App\Http\Controllers\SiteController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\SiteRequestController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WhatsappInboxController;
use App\Support\PublicPages;
use App\Support\Seo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', [SiteController::class, 'home'])->name('home');
Route::get('sistemas', [SiteController::class, 'systems'])->name('sistemas');
Route::get('sistemas/{product}', [SiteController::class, 'system'])
    ->whereIn('product', array_keys(PublicPages::PRODUCTS))
    ->name('sistemas.show');
Route::redirect('produtos', '/sistemas', 301);
Route::get('servicos', [SiteController::class, 'services'])->name('servicos');
foreach (array_keys(PublicPages::SERVICES) as $serviceSlug) {
    Route::get($serviceSlug, [SiteController::class, 'service'])
        ->name($serviceSlug === 'desenvolvimento-de-sites-para-empresas' ? 'company-websites' : 'servicos.'.$serviceSlug);
}
Route::get('sobre', [SiteController::class, 'about'])->name('sobre');
Route::get('contato', [ContactController::class, 'create'])->name('contato');
Route::post('contato', [ContactController::class, 'store'])->middleware('throttle:5,1')->name('contato.store');
Route::get('politica-de-privacidade', function () {
    return Inertia::render('legal/privacidade', [
        'seo' => Seo::tags([
            'title' => 'Política de Privacidade | ABrasil Sistemas',
            'description' => 'Como a ABrasil Sistemas coleta, usa e protege dados pessoais no site e nos formulários de contato.',
            'canonical' => url('/politica-de-privacidade'),
            'robots' => 'noindex, follow',
        ]),
    ]);
})->name('legal.privacidade');
Route::get('termos-de-uso', function () {
    return Inertia::render('legal/termos', [
        'seo' => Seo::tags([
            'title' => 'Termos de Uso | ABrasil Sistemas',
            'description' => 'Termos de uso do site da ABrasil Sistemas.',
            'canonical' => url('/termos-de-uso'),
            'robots' => 'noindex, follow',
        ]),
    ]);
})->name('legal.termos');
Route::get('politica-de-cookies', function () {
    return Inertia::render('legal/cookies', [
        'seo' => Seo::tags([
            'title' => 'Política de Cookies | ABrasil Sistemas',
            'description' => 'Como a ABrasil Sistemas usa cookies no site.',
            'canonical' => url('/politica-de-cookies'),
            'robots' => 'noindex, follow',
        ]),
    ]);
})->name('legal.cookies');
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

    return to_route('atendimento.index');
})->name('restricted-area');
Route::redirect('conhecimento', '/blog', 301);
Route::redirect('conhecimento/{path}', '/blog', 301)->where('path', '.*');
Route::redirect('minha-biblioteca', '/blog', 301);
Route::post('webhooks/mercadopago/ebooks/{token}', EbookMercadoPagoWebhookController::class)->name('webhooks.mercadopago.ebooks');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('blog/{post:slug}/comentarios', [BlogCommentController::class, 'store'])->name('blog.comments.store');
    Route::middleware('admin')->group(function () {
        // Painel do site institucional: atendimento + solicitações + conteúdo. Prospecção, funil,
        // importação e disparos comerciais ficam no VetorLeads (ABRASIL-WEB-03).
        Route::redirect('admin', '/atendimento')->name('admin');
        Route::get('atendimento', [WhatsappInboxController::class, 'index'])->name('atendimento.index');
        Route::post('atendimento/{conversation}/mensagens', [WhatsappInboxController::class, 'reply'])->middleware('throttle:30,1')->name('atendimento.reply');
        Route::get('solicitacoes', [SiteRequestController::class, 'index'])->name('solicitacoes.index');
        Route::get('solicitacoes/{solicitacao}', [SiteRequestController::class, 'show'])->name('solicitacoes.show');
        Route::patch('solicitacoes/{solicitacao}/situacao', [SiteRequestController::class, 'updateStatus'])->name('solicitacoes.status');
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
