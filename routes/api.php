<?php

use App\Http\Controllers\Api\ProspectImportController;
use App\Http\Controllers\Api\WhatsappActivityController;
use Illuminate\Support\Facades\Route;

Route::post('prospects/import', ProspectImportController::class)
    ->middleware(['throttle:30,1', 'prospect.token'])
    ->name('api.prospects.import');

Route::post('prospects/{lead}/whatsapp/log', [WhatsappActivityController::class, 'store'])
    ->middleware(['throttle:30,1', 'prospect.token'])
    ->name('api.prospects.whatsapp.log');
