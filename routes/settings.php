<?php

use App\Http\Controllers\Settings\CompanyWhatsappConnectionController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use App\Http\Controllers\Settings\WhatsappSettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])
        ->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::inertia('settings/appearance', 'settings/appearance')->name('appearance.edit');

    // WhatsApp institucional do atendimento (antiga "Configurações → Leads").
    Route::middleware('admin')->group(function () {
        Route::get('settings/whatsapp', [WhatsappSettingsController::class, 'edit'])->name('whatsapp-settings.edit');
        Route::put('settings/whatsapp', [WhatsappSettingsController::class, 'update'])->name('whatsapp-settings.update');

        Route::controller(CompanyWhatsappConnectionController::class)->group(function () {
            Route::get('settings/whatsapp/status', 'status')->name('whatsapp-settings.status');
            Route::post('settings/whatsapp/connect', 'connect')->middleware('throttle:10,1')->name('whatsapp-settings.connect');
            Route::get('settings/whatsapp/qr', 'qr')->name('whatsapp-settings.qr');
            Route::post('settings/whatsapp/disconnect', 'disconnect')->middleware('throttle:10,1')->name('whatsapp-settings.disconnect');
        });
    });
});
