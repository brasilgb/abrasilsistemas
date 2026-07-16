<?php

use App\Http\Controllers\LeadActivityController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
    Route::resource('leads', LeadController::class)->only(['index', 'create', 'store', 'edit', 'update']);
    Route::post('leads/{lead}/activities', [LeadActivityController::class, 'store'])->name('leads.activities.store');
    Route::resource('users', UserController::class)->only(['index', 'create', 'store', 'edit', 'update']);
});

require __DIR__.'/settings.php';
