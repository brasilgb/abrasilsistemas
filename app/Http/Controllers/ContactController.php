<?php

namespace App\Http\Controllers;

use App\Http\Requests\PublicContactRequest;
use App\Mail\ContactFormReceived;
use App\Models\Lead;
use App\Services\Seo\StructuredData;
use App\Services\SiteRequestIntake;
use App\Services\VisitOrigin;
use App\Services\WhatsappInbox;
use App\Support\Seo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class ContactController extends Controller
{
    public function create(Request $request): Response
    {
        // ?tipo=automacao pré-seleciona o tipo de projeto (links das páginas de serviço).
        $selected = $request->query('tipo');

        return Inertia::render('contato', [
            'projectTypes' => Lead::CONTACT_PROJECT_TYPES,
            'selectedType' => is_string($selected) && isset(Lead::CONTACT_PROJECT_TYPES[$selected]) ? $selected : null,
            'seo' => Seo::page('contato', ['jsonLd' => app(StructuredData::class)->forPage('contato')]),
        ]);
    }

    public function store(PublicContactRequest $request, SiteRequestIntake $intake, VisitOrigin $origin, WhatsappInbox $inbox): RedirectResponse
    {
        try {
            ['lead' => $lead, 'resubmission' => $resubmission] = $intake->store(
                $request->safe()->except(['website_hp', 'marketing_consent']),
                $origin->current($request),
                $request->boolean('marketing_consent'),
            );
        } catch (\Throwable $e) {
            report($e);

            return back()->withInput($request->except('website_hp'))->withErrors([
                'form' => 'Não foi possível registrar sua mensagem agora. Tente novamente em instantes ou escreva para '.config('contact.email').'.',
            ]);
        }

        // Daqui em diante a solicitação já está gravada: falhas não podem virar erro para o visitante.
        if (! $resubmission) {
            rescue(fn () => $inbox->linkSiteRequest($lead));
            $this->notifyTeam($lead, $request->string('product')->toString(), $request->string('notes')->toString());
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Mensagem enviada! Nossa equipe vai entrar em contato em breve.',
        ]);

        return back();
    }

    /**
     * Notify the team by e-mail via the SMTP configured in .env. The request is
     * already saved regardless, so a mail failure (bad SMTP
     * credentials, provider outage) must not break the visitor's submission.
     */
    private function notifyTeam(Lead $lead, string $product, string $message): void
    {
        try {
            Mail::to(config('contact.email'))->queue(new ContactFormReceived($lead, $product, $message));
        } catch (\Throwable $e) {
            Log::error('Falha ao enviar e-mail de notificação de contato.', [
                'lead_id' => $lead->id,
                'exception' => $e::class,
            ]);
        }
    }
}
