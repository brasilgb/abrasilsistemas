<?php

namespace App\Http\Controllers;

use App\Http\Requests\PublicContactRequest;
use App\Mail\ContactFormReceived;
use App\Models\Lead;
use App\Services\WhatsappInbox;
use App\Support\PublicPages;
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
            'seo' => Seo::tags([
                'title' => 'Fale sobre o seu projeto | ABrasil Sistemas',
                'description' => 'Conte o que sua empresa precisa: sistema pronto ou sob medida, site, landing page, aplicativo, automação ou integração. Resposta direta da equipe.',
                'canonical' => url('/contato'),
                ...PublicPages::heroSeo('contato'),
                'jsonLd' => [
                    '@context' => 'https://schema.org',
                    '@graph' => [PublicPages::organization(), ['@type' => 'ContactPage', 'url' => url('/contato')]],
                ],
            ]),
        ]);
    }

    public function store(PublicContactRequest $request, WhatsappInbox $inbox): RedirectResponse
    {
        $data = $request->safe()->except('website_hp');
        $duplicate = Lead::findSiteRequestDuplicate($data);

        if ($duplicate === null) {
            $lead = Lead::query()->create([
                ...$data,
                'status' => 'new',
                'request_status' => 'new',
                'source' => Lead::SOURCE_SITE,
            ]);
        } else {
            // A mesma pessoa voltou a escrever: a solicitação reabre como nova, com a mensagem anexada.
            $lead = $duplicate;
            $lead->forceFill([
                'request_status' => 'new',
                'source_page' => $lead->source_page ?? $data['source_page'] ?? null,
                'last_contacted_at' => now(),
                'notes' => trim(implode(PHP_EOL.PHP_EOL, array_filter([
                    $lead->notes,
                    '[Novo contato pelo site em '.now()->format('d/m/Y H:i').']'.PHP_EOL.$data['notes'],
                ]))),
            ])->save();

            $lead->activities()->create([
                'type' => 'note',
                'contacted_at' => now(),
                'description' => 'Novo contato recebido pelo formulário do site: '.$data['notes'],
            ]);
        }

        $inbox->linkSiteRequest($lead);
        $this->notifyTeam($lead);

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
    private function notifyTeam(Lead $lead): void
    {
        try {
            Mail::to(config('contact.email'))->queue(new ContactFormReceived($lead));
        } catch (\Throwable $e) {
            Log::error('Falha ao enviar e-mail de notificação de contato.', [
                'lead_id' => $lead->id,
                'exception' => $e::class,
            ]);
        }
    }
}
