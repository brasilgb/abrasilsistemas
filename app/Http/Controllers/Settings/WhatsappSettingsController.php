<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\CompanyWhatsappRequest;
use App\Services\CompanyWhatsappSettings;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Configurações → WhatsApp: o WhatsApp institucional usado pelo atendimento (/atendimento).
 * A conexão com o WAHA (status, QR Code, conectar, desconectar) fica em
 * CompanyWhatsappConnectionController. Segredos (API key, chave HMAC) nunca vão ao navegador.
 */
class WhatsappSettingsController extends Controller
{
    public function edit(CompanyWhatsappSettings $companyWhatsapp): Response
    {
        return Inertia::render('settings/whatsapp', [
            'companyWhatsapp' => $companyWhatsapp->current(),
            'whatsappProviders' => CompanyWhatsappSettings::PROVIDERS,
            // Só se o recebimento está ligado; a chave em si fica no servidor.
            'webhookEnabled' => filled(config('services.waha.webhook_hmac_key')),
            'webhookUrl' => route('api.whatsapp.waha.webhook'),
        ]);
    }

    public function update(CompanyWhatsappRequest $request, CompanyWhatsappSettings $companyWhatsapp): RedirectResponse
    {
        $companyWhatsapp->save([
            'enabled' => $request->boolean('enabled'),
            'number' => $request->validated('number'),
            'provider' => $request->validated('provider'),
            'session' => $request->validated('session'),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'WhatsApp do atendimento salvo.',
        ]);

        return to_route('whatsapp-settings.edit');
    }
}
