<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LeadActivityController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::redirect('admin', '/dashboard')->name('admin');
    Route::post('leads/import', [LeadController::class, 'import'])->name('leads.import');
    Route::patch('leads/{lead}/status', [LeadController::class, 'status'])->name('leads.status');
    Route::resource('leads', LeadController::class)->only(['index', 'create', 'store', 'edit', 'update']);
    Route::post('leads/{lead}/activities', [LeadActivityController::class, 'store'])->name('leads.activities.store');
    Route::resource('users', UserController::class)->only(['index', 'create', 'store', 'edit', 'update']);
});

require __DIR__.'/settings.php';
