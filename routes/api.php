<?php

use App\Http\Controllers\Api\LeadAutomationExecutionController;
use App\Http\Controllers\Api\LeadAutomationStateController;
use App\Http\Controllers\Api\ProspectImportController;
use App\Http\Controllers\Api\WhatsappActivityController;
use App\Http\Controllers\Api\WhatsappExternalOutboundController;
use App\Http\Controllers\Api\WhatsappInboundController;
use App\Http\Controllers\Api\WhatsappMessageStatusController;
use Illuminate\Support\Facades\Route;

Route::post('prospects/import', ProspectImportController::class)
    ->middleware(['throttle:30,1', 'prospect.token'])
    ->name('api.prospects.import');

Route::get('prospects/{lead}/automation-state', [LeadAutomationStateController::class, 'show'])
    ->middleware(['throttle:120,1', 'prospect.token'])
    ->name('api.prospects.automation-state');

Route::post('prospects/{lead}/automation-executions/reserve', [LeadAutomationExecutionController::class, 'reserve'])
    ->middleware(['throttle:120,1', 'prospect.token'])
    ->name('api.prospects.automation-executions.reserve');

Route::post('prospects/{lead}/whatsapp/log', [WhatsappActivityController::class, 'store'])
    ->middleware(['throttle:30,1', 'prospect.token'])
    ->name('api.prospects.whatsapp.log');

Route::patch('whatsapp/messages/{providerMessageId}/status', [WhatsappMessageStatusController::class, 'update'])
    ->middleware(['throttle:120,1', 'prospect.token'])
    ->name('api.whatsapp.messages.status');

Route::post('whatsapp/inbound', [WhatsappInboundController::class, 'store'])
    ->middleware(['throttle:120,1', 'prospect.token'])
    ->name('api.whatsapp.inbound');

Route::post('whatsapp/outbound-external', [WhatsappExternalOutboundController::class, 'store'])
    ->middleware(['throttle:120,1', 'prospect.token'])
    ->name('api.whatsapp.outbound-external');
