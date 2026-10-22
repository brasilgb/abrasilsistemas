<?php

use App\Http\Controllers\Admin\BlogCategoryController as AdminBlogCategoryController;
use App\Http\Controllers\Admin\BlogCommentController as AdminBlogCommentController;
use App\Http\Controllers\Admin\BlogImageController as AdminBlogImageController;
use App\Http\Controllers\Admin\BlogPostController as AdminBlogPostController;
use App\Http\Controllers\Admin\PortfolioImageController;
use App\Http\Controllers\Admin\PortfolioItemController;
use App\Http\Controllers\Admin\Seo\AnalyzeController as SeoAnalyzeController;
use App\Http\Controllers\Admin\Seo\AuditController as SeoAuditController;
use App\Http\Controllers\Admin\Seo\IndexingController as SeoIndexingController;
use App\Http\Controllers\Admin\Seo\IntegrationController as SeoIntegrationController;
use App\Http\Controllers\Admin\Seo\KeywordController as SeoKeywordController;
use App\Http\Controllers\Admin\Seo\OverviewController as SeoOverviewController;
use App\Http\Controllers\Admin\Seo\PageController as SeoPageController;
use App\Http\Controllers\Admin\Seo\RedirectController as SeoRedirectController;
use App\Http\Controllers\Admin\Seo\SettingsController as SeoSettingsController;
use App\Http\Controllers\Admin\Seo\StructuredDataController as SeoStructuredDataController;
use App\Http\Controllers\Admin\TestimonialController;
use App\Http\Controllers\Admin\TestimonialImageController;
use App\Http\Controllers\BlogCommentController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\EbookMercadoPagoWebhookController;
use App\Http\Controllers\IndexNowKeyController;
use App\Http\Controllers\PublicBlogController;
use App\Http\Controllers\RobotsController;
use App\Http\Controllers\SiteController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\SiteRequestController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WhatsappInboxController;
use App\Support\PublicPages;
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
Route::get('politica-de-privacidade', [SiteController::class, 'legal'])->defaults('page', 'privacidade')->name('legal.privacidade');
Route::get('termos-de-uso', [SiteController::class, 'legal'])->defaults('page', 'termos')->name('legal.termos');
Route::get('politica-de-cookies', [SiteController::class, 'legal'])->defaults('page', 'cookies')->name('legal.cookies');
Route::get('sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');
Route::get('sitemaps/{part}.xml', [SitemapController::class, 'part'])->whereNumber('part')->name('sitemap.part');
Route::get('robots.txt', RobotsController::class)->name('robots');
Route::get('{key}.txt', IndexNowKeyController::class)->where('key', '[a-f0-9]{32}')->name('indexnow.key');
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

        // SEO (ABRASIL-SEO-02).
        Route::prefix('admin/seo')->name('admin.seo.')->group(function () {
            Route::get('/', SeoOverviewController::class)->name('overview');
            Route::get('auditoria', [SeoAuditController::class, 'index'])->name('audits.index');
            Route::post('auditoria', [SeoAuditController::class, 'store'])->middleware('throttle:6,1')->name('audits.store');
            Route::get('auditoria/{audit}', [SeoAuditController::class, 'show'])->name('audits.show');
            Route::get('paginas', [SeoPageController::class, 'index'])->name('pages.index');
            Route::get('paginas/{key}', [SeoPageController::class, 'edit'])->where('key', '(page|post):[a-z0-9:_-]+')->name('pages.edit');
            Route::put('paginas/{key}', [SeoPageController::class, 'update'])->where('key', 'page:[a-z0-9:_-]+')->name('pages.update');
            Route::post('analisar', SeoAnalyzeController::class)->middleware('throttle:60,1')->name('analyze');
            Route::get('palavras-chave', SeoKeywordController::class)->name('keywords');
            Route::get('sitemap', SeoIndexingController::class)->name('indexing');
            Route::resource('redirecionamentos', SeoRedirectController::class)->except('show')
                ->parameters(['redirecionamentos' => 'redirect'])->names('redirects');
            Route::get('dados-estruturados', SeoStructuredDataController::class)->name('structured-data');
            Route::get('integracoes', [SeoIntegrationController::class, 'index'])->name('integrations');
            Route::put('integracoes', [SeoIntegrationController::class, 'update'])->name('integrations.update');
            Route::get('integracoes/google/conectar', [SeoIntegrationController::class, 'connect'])->name('integrations.google.connect');
            Route::get('integracoes/google/callback', [SeoIntegrationController::class, 'callback'])->name('integrations.google.callback');
            Route::delete('integracoes/google', [SeoIntegrationController::class, 'disconnect'])->name('integrations.google.disconnect');
            Route::get('configuracoes', [SeoSettingsController::class, 'edit'])->name('settings');
            Route::put('configuracoes', [SeoSettingsController::class, 'update'])->name('settings.update');
        });

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
