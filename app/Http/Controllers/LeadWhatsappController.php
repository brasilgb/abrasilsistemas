<?php

namespace App\Http\Controllers;

use App\Http\Requests\Leads\SendLeadWhatsappRequest;
use App\Models\Lead;
use App\Services\LeadWhatsappException;
use App\Services\LeadWhatsappService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class LeadWhatsappController extends Controller
{
    /**
     * Envio manual pelo operador. O destino e o prospect vêm do Lead da rota,
     * nunca do body; a LeadActivity é registrada pelo n8n, não aqui.
     */
    public function store(SendLeadWhatsappRequest $request, Lead $lead, LeadWhatsappService $whatsapp): RedirectResponse
    {
        try {
            $whatsapp->send($lead, $request->validated('message'));
        } catch (LeadWhatsappException $exception) {
            throw ValidationException::withMessages([$exception->field => $exception->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Mensagem enviada para o WhatsApp.']);

        return back();
    }
}
