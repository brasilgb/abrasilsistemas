<?php

namespace App\Http\Controllers;

use App\Http\Requests\PublicContactRequest;
use App\Mail\ContactFormReceived;
use App\Models\Lead;
use App\Support\Seo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class ContactController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('contato', [
            'products' => Lead::PRODUCTS,
            'seo' => Seo::tags([
                'title' => 'Contato | ABrasil Sistemas',
                'description' => 'Fale com a ABrasil Sistemas sobre um site, um sistema sob medida, o VetorOS ou o VetorPet.',
                'canonical' => url('/contato'),
            ]),
        ]);
    }

    public function store(PublicContactRequest $request): RedirectResponse
    {
        $data = $request->safe()->except('website_hp');

        if (Lead::isDuplicate($data)) {
            $lead = new Lead([...$data, 'status' => 'new', 'source' => 'site']);
        } else {
            $lead = Lead::query()->create([
                ...$data,
                'status' => 'new',
                'source' => 'site',
            ]);
        }

        $this->notifyTeam($lead);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Mensagem enviada! Nossa equipe vai entrar em contato em breve.',
        ]);

        return back();
    }

    /**
     * Notify the team by e-mail via the SMTP configured in .env. The lead is
     * already saved in the CRM regardless, so a mail failure (bad SMTP
     * credentials, provider outage) must not break the visitor's submission.
     */
    private function notifyTeam(Lead $lead): void
    {
        try {
            Mail::to(config('contact.email'))->send(new ContactFormReceived($lead));
        } catch (\Throwable $e) {
            Log::error('Falha ao enviar e-mail de notificação de contato.', [
                'lead_id' => $lead->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
