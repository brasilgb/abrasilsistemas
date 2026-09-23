<?php

use App\Http\Controllers\Api\ProspectImportController;
use App\Http\Controllers\Api\WhatsappActivityController;
use App\Http\Controllers\Api\WhatsappMessageStatusController;
use Illuminate\Support\Facades\Route;

Route::post('prospects/import', ProspectImportController::class)
    ->middleware(['throttle:30,1', 'prospect.token'])
    ->name('api.prospects.import');

Route::post('prospects/{lead}/whatsapp/log', [WhatsappActivityController::class, 'store'])
    ->middleware(['throttle:30,1', 'prospect.token'])
    ->name('api.prospects.whatsapp.log');

Route::patch('whatsapp/messages/{providerMessageId}/status', [WhatsappMessageStatusController::class, 'update'])
    ->middleware(['throttle:120,1', 'prospect.token'])
    ->name('api.whatsapp.messages.status');
