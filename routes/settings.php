<?php

use App\Http\Controllers\Settings\LeadSettingsController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
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
    Route::get('settings/leads', [LeadSettingsController::class, 'edit'])
        ->middleware('admin')
        ->name('lead-settings.edit');
    Route::put('settings/leads', [LeadSettingsController::class, 'update'])
        ->middleware('admin')
        ->name('lead-settings.update');
    Route::put('settings/leads/whatsapp', [LeadSettingsController::class, 'updateWhatsapp'])
        ->middleware('admin')
        ->name('lead-settings.whatsapp.update');
});
