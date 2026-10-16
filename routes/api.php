<?php

use App\Http\Controllers\Api\ProspectImportController;
use App\Http\Controllers\Api\WahaWebhookController;
use App\Http\Controllers\Api\WhatsappActivityController;
use App\Http\Controllers\Api\WhatsappMessageStatusController;
use Illuminate\Support\Facades\Route;

/*
 * LEGADO, mantido de propósito (ABRASIL-WEB-03). Estas três rotas são do CRM antigo e pertencem
 * ao VetorLeads, mas ainda podem ser chamadas de fora:
 * - prospects/import: a extensão AB Prospect (inclusive a v1.4.0 do VetorLeads) vem configurada
 *   com https://abrasilsistemas.com.br/api/prospects/import;
 * - prospects/{lead}/whatsapp/log e whatsapp/messages/{id}/status: workflows do n8n
 *   ("CRM ABrasil - Enviar WhatsApp" e o de ACK), cujo destino não foi confirmado.
 * Só saem depois que essas integrações apontarem para o VetorLeads. O site não usa nenhuma delas.
 */
Route::post('prospects/import', ProspectImportController::class)
    ->middleware(['throttle:30,1', 'prospect.token'])
    ->name('api.prospects.import');

Route::post('prospects/{lead}/whatsapp/log', [WhatsappActivityController::class, 'store'])
    ->middleware(['throttle:30,1', 'prospect.token'])
    ->name('api.prospects.whatsapp.log');

Route::patch('whatsapp/messages/{providerMessageId}/status', [WhatsappMessageStatusController::class, 'update'])
    ->middleware(['throttle:120,1', 'prospect.token'])
    ->name('api.whatsapp.messages.status');

// Atendimento institucional: webhook direto do WAHA (sessão do WhatsApp da empresa), sem n8n.
Route::post('whatsapp/waha/webhook', WahaWebhookController::class)
    ->middleware('throttle:600,1')
    ->name('api.whatsapp.waha.webhook');
