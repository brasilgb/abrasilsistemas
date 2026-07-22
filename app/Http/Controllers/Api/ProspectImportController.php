<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProspectImportController extends Controller
{
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
            $ids = [];

            foreach ($validated['prospects'] as $prospect) {
                $whatsapp = trim((string) ($prospect['whatsapp'] ?? ''));

                if ($whatsapp === '') {
                    $whatsapp = $prospect['phone'] ?? null;
                }

                $attributes = [
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
                    'source' => $validated['source'],
                    'product' => 'vetoros',
                    'status' => 'new',
                ];

                $lead = ! empty($prospect['mapsUrl'])
                    ? Lead::query()->firstOrNew(['maps_url' => $prospect['mapsUrl']])
                    : new Lead;

                $lead->fill($attributes);
                $lead->exists ? $updated++ : $created++;
                $lead->save();
                $ids[] = $lead->id;
            }

            return compact('created', 'updated', 'ids');
        });

        return response()->json([
            'message' => 'Leads importados com sucesso.',
            ...$result,
        ], $result['created'] > 0 ? 201 : 200);
    }
}
