<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EbookDownloadController;
use App\Http\Controllers\EbookLibraryController;
use App\Http\Controllers\EbookMercadoPagoWebhookController;
use App\Http\Controllers\EbookOrderStatusController;
use App\Http\Controllers\EbookPixCheckoutController;
use App\Http\Controllers\KnowledgeVolumeController;
use App\Http\Controllers\LeadActivityController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');
Route::inertia('conhecimento', 'knowledge')->name('knowledge');
Route::get('conhecimento/volumes/{volume}', KnowledgeVolumeController::class)->name('knowledge.volumes.show');
Route::post('webhooks/mercadopago/ebooks/{token}', EbookMercadoPagoWebhookController::class)->name('webhooks.mercadopago.ebooks');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::redirect('admin', '/dashboard')->name('admin');
    Route::post('leads/import', [LeadController::class, 'import'])->name('leads.import');
    Route::patch('leads/{lead}/status', [LeadController::class, 'status'])->name('leads.status');
    Route::resource('leads', LeadController::class)->only(['index', 'create', 'store', 'edit', 'update']);
    Route::post('leads/{lead}/activities', [LeadActivityController::class, 'store'])->name('leads.activities.store');
    Route::resource('users', UserController::class)->only(['index', 'create', 'store', 'edit', 'update']);
    Route::get('minha-biblioteca', EbookLibraryController::class)->name('ebooks.library');
    Route::get('minha-biblioteca/{ebook}/download', EbookDownloadController::class)->name('ebooks.download');
    Route::post('conhecimento/ebooks/{ebook}/pix', EbookPixCheckoutController::class)->name('ebooks.pix.store');
    Route::get('conhecimento/pedidos/{order}/status', EbookOrderStatusController::class)->name('ebooks.orders.status');
});

require __DIR__.'/settings.php';
