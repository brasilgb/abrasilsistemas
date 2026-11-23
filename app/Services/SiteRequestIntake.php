<?php

namespace App\Services;

use App\Models\Lead;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Grava a solicitação do formulário do site. O formulário é público e não prova quem está enviando:
 * - um envio só entra numa solicitação existente se ela estiver em aberto e já tiver todos os contatos
 *   informados (ver Lead::findOpenSiteRequest); nesse caso ele vira uma linha do histórico e não muda
 *   nome, empresa, contatos, serviço, origem nem situação;
 * - em qualquer outro caso nasce uma solicitação nova;
 * - o reenvio da mesma mensagem (duplo clique, nova tentativa) não gera registro nem aviso.
 */
class SiteRequestIntake
{
    /** Janela em que a mesma mensagem, da mesma pessoa, é tratada como reenvio. */
    private const RESUBMIT_MINUTES = 30;

    /**
     * @param  array<string, mixed>  $data  dados validados do formulário
     * @param  array<string, string|null>  $origin  VisitOrigin::current()
     * @return array{lead: Lead, resubmission: bool}
     */
    public function store(array $data, array $origin, bool $marketingConsent): array
    {
        $key = 'site-request:'.sha1(($data['email'] ?? '').'|'.($data['whatsapp'] ?? ''));

        return Cache::lock($key, 15)->block(5, fn () => DB::transaction(fn () => $this->persist($data, $origin, $marketingConsent)));
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, string|null>  $origin
     * @return array{lead: Lead, resubmission: bool}
     */
    private function persist(array $data, array $origin, bool $marketingConsent): array
    {
        $lead = Lead::findOpenSiteRequest($data);

        if ($lead === null) {
            $lead = Lead::query()->create([
                ...$data,
                ...$origin,
                'marketing_consent_at' => $marketingConsent ? now() : null,
                'status' => 'new',
                'request_status' => 'new',
                'source' => Lead::SOURCE_SITE,
            ]);

            return ['lead' => $lead, 'resubmission' => false];
        }

        if ($marketingConsent && $lead->marketing_consent_at === null) {
            $lead->forceFill(['marketing_consent_at' => now()])->save();
        }

        if ($this->isResubmission($lead, $data)) {
            return ['lead' => $lead, 'resubmission' => true];
        }

        $lead->forceFill(['last_contacted_at' => now()])->save();
        $lead->activities()->create([
            'type' => 'contact',
            'contacted_at' => now(),
            'description' => $this->describe($data, $origin),
        ]);

        return ['lead' => $lead, 'resubmission' => false];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function isResubmission(Lead $lead, array $data): bool
    {
        $since = now()->subMinutes(self::RESUBMIT_MINUTES);

        if ($lead->product === $data['product'] && $lead->notes === $data['notes'] && $lead->created_at?->gte($since)) {
            return true;
        }

        return $lead->activities()
            ->where('type', 'contact')
            ->where('created_at', '>=', $since)
            ->pluck('description')
            ->contains(fn (string $description) => str_ends_with($description, $this->message($data)));
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, string|null>  $origin
     */
    private function describe(array $data, array $origin): string
    {
        $details = array_filter([
            'Nome informado: '.$data['contact_name'],
            filled($data['company_name'] ?? null) ? 'empresa: '.$data['company_name'] : null,
            filled($data['source_page'] ?? null) ? 'página: '.$data['source_page'] : null,
            'origem: '.VisitOrigin::label($origin),
        ]);

        return Str::ucfirst(implode('; ', $details)).'.'.PHP_EOL.$this->message($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function message(array $data): string
    {
        return 'Serviço: '.(Lead::CONTACT_PROJECT_TYPES[$data['product']] ?? $data['product']).PHP_EOL.$data['notes'];
    }
}
