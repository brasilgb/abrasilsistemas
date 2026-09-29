<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Services\CompanyWhatsappConnection;
use App\Services\LeadWhatsappService;
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
     * Resultado da análise do site pela extensão. Vale em grupo e só enquanto o lead nunca foi
     * analisado (site_status vazio): uma captura sem análise não desfaz a anterior.
     */
    private const SITE_ANALYSIS = [
        'site_status' => 'situação do site',
        'has_website' => 'tem site',
        'can_improve' => 'pode melhorar',
    ];

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
        ]);

        $result = DB::transaction(function () use ($validated): array {
            $created = 0;
            $updated = 0;
            $unchanged = 0;
            $ids = [];

            foreach ($validated['prospects'] as $prospect) {
                $attributes = $this->captureAttributes($prospect);
                $lead = $this->findExistingLead($attributes);

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

                    if (! $this->mergeCapture($lead, $attributes, $validated['source'])) {
                        $unchanged++;
                    }
                }

                $ids[] = $lead->id;
            }

            return compact('created', 'updated', 'unchanged', 'ids');
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
        $whatsapp = trim((string) ($prospect['whatsapp'] ?? ''));

        if ($whatsapp === '') {
            $whatsapp = $prospect['phone'] ?? null;
        }

        return [
            'company_name' => $prospect['name'],
            'address' => $prospect['address'] ?? null,
            'phone' => $prospect['phone'] ?? null,
            'whatsapp' => $whatsapp,
            'website' => $prospect['website'] ?? null,
            'has_website' => $prospect['hasWebsite'],
            'site_status' => $prospect['siteStatus'] ?? null,
            'can_improve' => $prospect['canImprove'],
            'opportunity' => $prospect['opportunity'] ?? null,
            'maps_url' => $prospect['mapsUrl'] ?? null,
            'city' => $prospect['city'] ?? null,
            'state' => isset($prospect['state']) ? strtoupper($prospect['state']) : null,
            'category' => $prospect['category'] ?? null,
            'industry' => $prospect['category'] ?? null,
            'rating' => $prospect['rating'] ?? null,
            'reviews' => $prospect['reviews'] ?? null,
            'captured_at' => $prospect['capturedAt'] ?? null,
        ];
    }

    /**
     * Lead que já representa o prospect: mesmo lugar do Maps; sem isso, mesmo telefone ou mesma
     * empresa na mesma cidade/UF, desde que não seja outro lugar do Maps.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function findExistingLead(array $attributes): ?Lead
    {
        $mapsUrl = $attributes['maps_url'];
        $placeKey = self::mapsPlaceKey($mapsUrl);

        if ($mapsUrl !== null) {
            $lead = Lead::query()->where('maps_url', $mapsUrl)->oldest('id')->first()
                ?? ($placeKey !== null
                    ? Lead::query()->where('maps_url', 'like', '%'.$placeKey.'%')->oldest('id')->first()
                    : null);

            if ($lead !== null) {
                return $lead;
            }
        }

        return $this->leadsWithSamePhone($attributes['whatsapp'])
            ->merge($this->leadsWithSameCompany($attributes))
            ->sortBy('id')
            ->first(fn (Lead $lead) => ! self::differentMapsPlace($lead->maps_url, $mapsUrl));
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

    /**
     * Aplica a nova captura a um lead existente sem tocar nos dados comerciais (status, responsável,
     * produto, origem, follow-up, perda, contato, observações). Retorna se algo mudou.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function mergeCapture(Lead $lead, array $attributes, string $source): bool
    {
        foreach (array_keys(self::FILL_IF_EMPTY) as $field) {
            if (blank($lead->{$field}) && filled($attributes[$field])) {
                $lead->{$field} = $attributes[$field];
            }
        }

        if (blank($lead->site_status) && filled($attributes['site_status'])) {
            foreach (array_keys(self::SITE_ANALYSIS) as $field) {
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
            self::FILL_IF_EMPTY + self::SITE_ANALYSIS,
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
     * Identificador do lugar no Google Maps (!1s0x…:0x… ou ftid=), igual em qualquer busca; a URL
     * inteira muda com o zoom/coordenadas (@lat,lng,z) e os parâmetros da busca.
     */
    private static function mapsPlaceKey(?string $url): ?string
    {
        if ($url === null || preg_match('/(?:!1s|ftid=)(0x[0-9a-f]+:0x[0-9a-f]+)/i', $url, $match) !== 1) {
            return null;
        }

        return strtolower($match[1]);
    }

    private static function differentMapsPlace(?string $a, ?string $b): bool
    {
        if (blank($a) || blank($b) || $a === $b) {
            return false;
        }

        $key = self::mapsPlaceKey($a);

        return $key === null || $key !== self::mapsPlaceKey($b);
    }
}
