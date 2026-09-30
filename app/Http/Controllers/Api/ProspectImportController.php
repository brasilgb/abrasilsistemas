<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Services\BrazilianPhone;
use App\Services\CompanyWhatsappConnection;
use App\Services\LeadWhatsappService;
use App\Services\MapsPlace;
use App\Services\WebsiteKind;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProspectImportController extends Controller
{
    /**
     * Dados de captação que o comercial também edita no formulário (pode ter corrigido nome,
     * WhatsApp etc.): numa reimportação só são completados quando o CRM ainda não tem valor.
     */
    private const FILL_IF_EMPTY = [
        'company_name' => 'nome',
        'address' => 'endereço',
        'phone' => 'telefone',
        'whatsapp' => 'WhatsApp',
        'website' => 'site',
        'city' => 'cidade',
        'state' => 'UF',
        'category' => 'categoria',
        'industry' => 'segmento',
        'opportunity' => 'oportunidade',
        'maps_url' => 'link do Maps',
    ];

    /**
     * Resultado da análise do site pela extensão. Vale em grupo e só enquanto o lead ainda não tem
     * análise (situação vazia ou "ainda não analisado", ou sem site até agora e a captura achou
     * um): uma captura sem análise não desfaz a anterior.
     */
    private const SITE_ANALYSIS = [
        'site_status' => 'situação do site',
        'has_website' => 'tem site',
        'can_improve' => 'pode melhorar',
        'website_checks' => 'checagens do site',
    ];

    /** Situação que a extensão envia antes de analisar um site: não é análise. */
    private const SITE_NOT_ANALYZED = 'Com site - ainda não analisado';

    /** Oportunidade padrão da extensão enquanto o site não foi analisado: a análise a substitui. */
    private const PENDING_OPPORTUNITY = 'Possui site. Recomenda-se analisar oportunidades de modernização e integração com a AB Sistemas.';

    /** Checagens do site-analyzer da extensão; chaves desconhecidas são ignoradas. */
    private const WEBSITE_CHECKS = ['accessible', 'https', 'viewport', 'metaDescription', 'whatsapp', 'contactForm', 'clientArea', 'quote'];

    /** Situação gravada quando o "site" do Maps não é site próprio (rede social, link na bio, WhatsApp). */
    private const NOT_OWN_SITE_STATUS = [
        WebsiteKind::SOCIAL => 'Sem site próprio - só rede social',
        WebsiteKind::LINK_BIO => 'Sem site próprio - só link na bio',
        WebsiteKind::WHATSAPP => 'Sem site próprio - só link de WhatsApp',
    ];

    private const NOT_OWN_SITE_OPPORTUNITY = 'O perfil no Maps aponta só para rede social, link na bio ou WhatsApp. Oportunidade para oferecer site próprio e soluções da AB Sistemas.';

    /** Identidade do lugar: completada quando falta, sem nota no Histórico (é dado técnico). */
    private const MAPS_IDENTITY = ['google_place_id', 'maps_feature_id', 'latitude', 'longitude'];

    /** Métricas do Maps: retrato da última captura, atualizadas sem nota no Histórico. */
    private const MAPS_SNAPSHOT = ['rating', 'reviews', 'captured_at'];

    public function __invoke(Request $request): JsonResponse
    {
        if (! $request->has('prospects')) {
            $prospect = $request->all();

            $request->merge([
                'source' => $request->input('source', 'AB Prospect - Google Maps'),
                'prospects' => [$prospect],
            ]);
        }

        $validated = $request->validate([
            'source' => ['required', 'string', 'max:255'],
            'product' => ['nullable', 'string', 'max:255'],
            'prospects' => ['required', 'array', 'min:1', 'max:100'],
            'prospects.*.name' => ['required', 'string', 'max:255'],
            'prospects.*.address' => ['nullable', 'string', 'max:500'],
            'prospects.*.phone' => ['nullable', 'string', 'max:255'],
            'prospects.*.whatsapp' => ['nullable', 'string', 'max:255'],
            'prospects.*.website' => ['nullable', 'url:http,https', 'max:2048'],
            'prospects.*.hasWebsite' => ['required', 'boolean'],
            'prospects.*.siteStatus' => ['nullable', 'string', 'max:255'],
            'prospects.*.canImprove' => ['required', 'boolean'],
            'prospects.*.opportunity' => ['nullable', 'string', 'max:5000'],
            'prospects.*.mapsUrl' => ['nullable', 'url:http,https', 'max:2048'],
            'prospects.*.city' => ['nullable', 'string', 'max:255'],
            'prospects.*.state' => ['nullable', 'string', 'size:2'],
            'prospects.*.category' => ['nullable', 'string', 'max:255'],
            'prospects.*.rating' => ['nullable', 'numeric', 'between:0,5'],
            'prospects.*.reviews' => ['nullable', 'integer', 'min:0'],
            'prospects.*.capturedAt' => ['nullable', 'date'],
            // Opcionais da extensão 1.4+; a 1.3 não envia e continua funcionando.
            'prospects.*.placeId' => ['nullable', 'string', 'max:255'],
            'prospects.*.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'prospects.*.longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'prospects.*.websiteChecks' => ['nullable', 'array'],
            'prospects.*.websiteChecks.*' => ['nullable', 'boolean'],
        ]);

        $result = DB::transaction(function () use ($validated): array {
            $created = 0;
            $updated = 0;
            $unchanged = 0;
            $restored = 0;
            $ids = [];

            foreach ($validated['prospects'] as $prospect) {
                $attributes = $this->captureAttributes($prospect);
                $lead = $this->findExistingLead($attributes);
                $wasTrashed = $lead?->trashed() ?? false;

                if ($lead === null) {
                    // Status, produto e origem só na criação: depois são dados do comercial.
                    $lead = Lead::query()->create([
                        ...$attributes,
                        'source' => $validated['source'],
                        'product' => isset($validated['product']) && trim($validated['product']) !== ''
                            ? strtolower(trim($validated['product']))
                            : 'vetoros',
                        'status' => 'new',
                    ]);
                    $created++;
                } else {
                    // "updated" continua sendo "já existia no CRM" (a extensão exibe esse total).
                    $updated++;

                    if ($wasTrashed) {
                        $this->restoreLead($lead, $validated['source']);
                        $restored++;
                    }

                    if (! $this->mergeCapture($lead, $attributes, $validated['source']) && ! $wasTrashed) {
                        $unchanged++;
                    }
                }

                $ids[] = $lead->id;
            }

            return compact('created', 'updated', 'unchanged', 'restored', 'ids');
        });

        return response()->json([
            'message' => 'Leads importados com sucesso.',
            ...$result,
        ], $result['created'] > 0 ? 201 : 200);
    }

    /**
     * @param  array<string, mixed>  $prospect
     * @return array<string, mixed>
     */
    private function captureAttributes(array $prospect): array
    {
        $phone = $prospect['phone'] ?? null;
        $whatsapp = trim((string) ($prospect['whatsapp'] ?? ''));

        // O telefone do Maps só vira WhatsApp quando tem cara de celular: fixo não é presumido.
        if ($whatsapp === '') {
            $whatsapp = BrazilianPhone::isProbableMobile($phone) ? $phone : null;
        }

        $mapsUrl = $prospect['mapsUrl'] ?? null;
        $place = MapsPlace::fromUrl($mapsUrl)->orElse(MapsPlace::fromValues(
            $prospect['placeId'] ?? null,
            $prospect['latitude'] ?? null,
            $prospect['longitude'] ?? null,
        ));

        $checks = is_array($prospect['websiteChecks'] ?? null)
            ? array_intersect_key($prospect['websiteChecks'], array_flip(self::WEBSITE_CHECKS))
            : [];

        $attributes = [
            'company_name' => $prospect['name'],
            'address' => $prospect['address'] ?? null,
            'phone' => $phone,
            'whatsapp' => $whatsapp,
            'website' => $prospect['website'] ?? null,
            'has_website' => $prospect['hasWebsite'],
            'site_status' => $prospect['siteStatus'] ?? null,
            'website_checks' => $checks === [] ? null : array_map(fn ($value) => $value === null ? null : (bool) $value, $checks),
            'can_improve' => $prospect['canImprove'],
            'opportunity' => $prospect['opportunity'] ?? null,
            'maps_url' => $mapsUrl,
            ...$place->toAttributes(),
            'city' => $prospect['city'] ?? null,
            'state' => isset($prospect['state']) ? strtoupper($prospect['state']) : null,
            'category' => $prospect['category'] ?? null,
            'industry' => $prospect['category'] ?? null,
            'rating' => $prospect['rating'] ?? null,
            'reviews' => $prospect['reviews'] ?? null,
            'captured_at' => $prospect['capturedAt'] ?? null,
        ];

        // Instagram, Facebook, link na bio ou wa.me no campo "site" não é site próprio (a extensão
        // 1.3 marcava como site e até analisava a página da rede social).
        $kind = WebsiteKind::of($attributes['website']);

        if ($kind !== null && $kind !== WebsiteKind::OWN) {
            $attributes['has_website'] = false;
            $attributes['can_improve'] = true;
            $attributes['site_status'] = self::NOT_OWN_SITE_STATUS[$kind];
            $attributes['website_checks'] = null;
            $attributes['opportunity'] = self::NOT_OWN_SITE_OPPORTUNITY;
            $attributes['instagram'] = WebsiteKind::isInstagram($attributes['website']) ? $attributes['website'] : null;
        }

        return $attributes;
    }

    /**
     * Lead que já representa o prospect, nesta ordem:
     *  1. mesmo lugar do Maps (Place ID, depois FTID), inclusive lead excluído (é restaurado);
     *  2. mesmo link do Maps (sem identificador), só entre os ativos;
     *  3. mesmo telefone ou mesma empresa na mesma cidade/UF, entre os ativos e desde que não seja
     *     outro lugar do Maps. Telefone ou nome nunca restauram um lead excluído.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function findExistingLead(array $attributes): ?Lead
    {
        $mapsUrl = $attributes['maps_url'];
        $place = new MapsPlace(
            $attributes['google_place_id'],
            $attributes['maps_feature_id'],
        );

        foreach ([new MapsPlace(placeId: $place->placeId), new MapsPlace(featureId: $place->featureId)] as $key) {
            if (! $key->hasIdentity()) {
                continue;
            }

            // Ativo primeiro; entre os excluídos, o mais antigo (o que tem o histórico).
            $lead = Lead::withTrashed()
                ->samePlace($key)
                ->orderByRaw('CASE WHEN deleted_at IS NULL THEN 0 ELSE 1 END')
                ->oldest('id')
                ->first();

            if ($lead !== null) {
                return $lead;
            }
        }

        if ($mapsUrl !== null) {
            $lead = Lead::query()->where('maps_url', $mapsUrl)->oldest('id')->first();

            if ($lead !== null) {
                return $lead;
            }
        }

        return $this->leadsWithSamePhone($attributes['whatsapp'])
            ->merge($this->leadsWithSamePhone($attributes['phone']))
            ->merge($this->leadsWithSameCompany($attributes))
            ->sortBy('id')
            ->first(fn (Lead $lead) => ! self::differentMapsPlace($lead, $mapsUrl, $place));
    }

    /**
     * @return Collection<int, Lead>
     */
    private function leadsWithSamePhone(?string $number): Collection
    {
        $digits = preg_replace('/\D+/', '', (string) $number) ?? '';

        if (strlen($digits) < 8) {
            return new Collection;
        }

        // Mesma equivalência de Lead::findByWhatsappNumber: com ou sem 55 e nono dígito, com ou
        // sem máscara. O LIKE nos 8 últimos dígitos só pré-filtra.
        $pattern = '%'.implode('%', str_split(substr($digits, -8)));
        $target = LeadWhatsappService::normalizeNumber($digits) ?? $digits;
        $matches = fn (?string $value) => filled($value) && CompanyWhatsappConnection::sameNumber(
            LeadWhatsappService::normalizeNumber($value) ?? $value,
            $target,
        );

        return Lead::query()
            ->where(fn ($query) => $query->where('whatsapp', 'like', $pattern)->orWhere('phone', 'like', $pattern))
            ->get()
            ->filter(fn (Lead $lead) => $matches($lead->whatsapp) || $matches($lead->phone));
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return Collection<int, Lead>
     */
    private function leadsWithSameCompany(array $attributes): Collection
    {
        $company = trim((string) $attributes['company_name']);
        $city = trim((string) $attributes['city']);
        $state = trim((string) $attributes['state']);

        if ($company === '' || $city === '' || $state === '') {
            return new Collection;
        }

        return Lead::query()
            ->whereRaw('LOWER(company_name) = ?', [mb_strtolower($company)])
            ->whereRaw('LOWER(city) = ?', [mb_strtolower($city)])
            ->where('state', $state)
            ->get();
    }

    /** Lead excluído recapturado no mesmo lugar do Maps: volta com o histórico e os dados comerciais. */
    private function restoreLead(Lead $lead, string $source): void
    {
        $lead->restore();

        $lead->activities()->create([
            'type' => 'note',
            'description' => "Lead restaurado pela reimportação ({$source}): mesmo lugar do Google Maps.",
        ]);
    }

    /**
     * Aplica a nova captura a um lead existente sem tocar nos dados comerciais (status, responsável,
     * produto, origem, follow-up, perda, contato, observações). Retorna se algo mudou.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function mergeCapture(Lead $lead, array $attributes, string $source): bool
    {
        // Decidido antes de completar os vazios: depende do site e da oportunidade que o CRM tinha.
        $applyAnalysis = $this->replacesSiteAnalysis($lead, $attributes);
        $replaceOpportunity = $applyAnalysis && filled($attributes['opportunity'])
            && (blank($lead->opportunity) || $lead->opportunity === self::PENDING_OPPORTUNITY);

        foreach (array_keys(self::FILL_IF_EMPTY) as $field) {
            if (blank($lead->{$field}) && filled($attributes[$field])) {
                $lead->{$field} = $attributes[$field];
            }
        }

        if (blank($lead->instagram) && filled($attributes['instagram'] ?? null)) {
            $lead->instagram = $attributes['instagram'];
        }

        if ($applyAnalysis) {
            foreach (array_keys(self::SITE_ANALYSIS) as $field) {
                $lead->{$field} = $attributes[$field];
            }

            if ($replaceOpportunity) {
                $lead->opportunity = $attributes['opportunity'];
            }
        }

        foreach (self::MAPS_IDENTITY as $field) {
            if ($lead->{$field} === null && $attributes[$field] !== null) {
                $lead->{$field} = $attributes[$field];
            }
        }

        foreach (self::MAPS_SNAPSHOT as $field) {
            if ($attributes[$field] !== null) {
                $lead->{$field} = $attributes[$field];
            }
        }

        if (! $lead->isDirty()) {
            return false;
        }

        $labels = array_values(array_intersect_key(
            self::FILL_IF_EMPTY + self::SITE_ANALYSIS + ['instagram' => 'Instagram'],
            $lead->getDirty(),
        ));

        $lead->save();

        if ($labels !== []) {
            $lead->activities()->create([
                'type' => 'note',
                'description' => "Reimportação ({$source}): dados de captação completados — ".implode(', ', $labels).'.',
            ]);
        }

        return true;
    }

    /**
     * A análise do site recebida substitui a do CRM quando o CRM ainda não tem análise: situação
     * vazia, "ainda não analisado" (e a captura traz análise de fato) ou lead sem site nenhum até
     * agora e a captura achou um.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function replacesSiteAnalysis(Lead $lead, array $attributes): bool
    {
        $incoming = $attributes['site_status'];

        if (blank($incoming) || $incoming === $lead->site_status) {
            return false;
        }

        return blank($lead->site_status)
            || ($lead->site_status === self::SITE_NOT_ANALYZED && $incoming !== self::SITE_NOT_ANALYZED)
            || (! $lead->has_website && blank($lead->website) && (bool) $attributes['has_website'] && filled($attributes['website']));
    }

    /**
     * Outro lugar do Maps: os dois lados têm link e são lugares diferentes pelos identificadores.
     * Link sem identificador comparável (ex.: URL de busca) também conta como outro lugar.
     */
    private static function differentMapsPlace(Lead $lead, ?string $mapsUrl, MapsPlace $place): bool
    {
        if (blank($lead->maps_url) || blank($mapsUrl) || $lead->maps_url === $mapsUrl) {
            return false;
        }

        return MapsPlace::fromUrl($lead->maps_url)->orElse($lead->mapsPlace())->samePlaceAs($place) !== true;
    }
}
